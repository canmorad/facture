<?php

namespace App\Services;

use App\Models\BankRemittance;
use App\Models\PaymentDocument;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankRemittanceWorkflowService
{
    public function __construct(
        protected BankRemittanceService $remittanceService,
        protected PaymentService $paymentService,
        protected NumberingService $numberingService,
    ) {}

    public function createRemittanceFromDocuments(array $documentIds, int $bankAccountId): BankRemittance
    {
        $companyId = $this->getCompanyId();

        DB::beginTransaction();

        try {
            $remittance = BankRemittance::create([
                'company_id' => $companyId,
                'bank_account_id' => $bankAccountId,
                'status' => 'DRAFT',
                'remittance_date' => now(),
                'total_amount' => 0,
                'document_count' => 0,
            ]);

            foreach ($documentIds as $documentId) {
                $remittance->addPaymentDocument($documentId);
            }

            $remittance->calculateTotals();

            DB::commit();

            return $remittance->fresh(['bankAccount', 'paymentDocuments']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('BankRemittanceWorkflowService create error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function finalizeRemittance(int $remittanceId): BankRemittance
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $remittanceId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$remittance->canBeFinalized()) {
            throw new \InvalidArgumentException('Cette remise ne peut pas être finalisée. Vérifiez qu\'elle contient des documents.');
        }

        $number = $this->numberingService->generateNumber('bank_remittance', $companyId);

        DB::beginTransaction();

        try {
            $remittance->update([
                'status' => 'FINALIZED',
                'number' => $number,
                'finalized_at' => now(),
            ]);

            $remittance->paymentDocuments->each(function ($doc) {
                if ($doc->status === 'pending' || $doc->status === 'returned') {
                    $doc->update(['status' => 'remitted']);
                }
            });

            DB::commit();

            return $remittance->fresh(['bankAccount', 'paymentDocuments']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('BankRemittanceWorkflowService finalize error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function markRemittanceSent(int $remittanceId): BankRemittance
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $remittanceId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$remittance->canBeSent()) {
            throw new \InvalidArgumentException('Cette remise ne peut pas être marquée comme envoyée.');
        }

        DB::beginTransaction();

        try {
            $remittance->update([
                'status' => 'SENT',
                'sent_at' => now(),
            ]);

            DB::commit();

            return $remittance->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function markRemittanceDeposited(int $remittanceId, ?string $depositSlipRef = null): BankRemittance
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $remittanceId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$remittance->canBeDeposited()) {
            throw new \InvalidArgumentException('Cette remise ne peut pas être marquée comme déposée.');
        }

        DB::beginTransaction();

        try {
            $remittance->update([
                'status' => 'DEPOSITED',
                'deposited_at' => now(),
                'deposit_slip_reference' => $depositSlipRef ?? $remittance->deposit_slip_reference,
            ]);

            $remittance->paymentDocuments->each(function ($doc) {
                $doc->markAsDeposited();
            });

            $this->updateRelatedInvoices($remittance->paymentDocuments->pluck('id')->toArray());

            DB::commit();

            return $remittance->fresh(['bankAccount', 'paymentDocuments']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('BankRemittanceWorkflowService markDeposited error: ' . $e->getMessage());
            throw $e;
        }
    }

    protected function updateRelatedInvoices(array $paymentDocumentIds): void
    {
        if (empty($paymentDocumentIds)) {
            return;
        }

        $paymentDocuments = PaymentDocument::whereIn('id', $paymentDocumentIds)
            ->with(['payment.invoice.document'])
            ->get();

        foreach ($paymentDocuments as $paymentDocument) {
            if (!$paymentDocument->payment || !$paymentDocument->payment->invoice) {
                continue;
            }

            $paymentDocument->payment->update(['status' => 'completed']);

            $invoice = $paymentDocument->payment->invoice;
            $document = $invoice->document;

            if (!$document) {
                continue;
            }

            $this->checkAndUpdateInvoiceStatus($invoice);
        }
    }

    protected function checkAndUpdateInvoiceStatus(Invoice $invoice): void
    {
        $totalPaid = Payment::where('invoice_id', $invoice->id)
            ->where('status', 'completed')
            ->sum('amount');

        $totalDeductions = $invoice->deductions()->sum('amount');
        $totalTtc = $invoice->document?->total_ttc ?? 0;

        $remainingAmount = max(0, $totalTtc - $totalDeductions - $totalPaid);

        if ($remainingAmount <= 0.01 && $invoice->status !== 'PAID') {
            $invoice->transitionTo('PAID');
            $invoice->update(['paid_at' => now()]);

            Log::info('Invoice marked as paid via bank remittance', [
                'invoice_id' => $invoice->id,
                'total_paid' => $totalPaid,
            ]);
        }
    }

    public function cancelRemittance(int $remittanceId): BankRemittance
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $remittanceId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$remittance->canBeCancelled()) {
            throw new \InvalidArgumentException('Cette remise ne peut plus être annulée.');
        }

        DB::beginTransaction();

        try {
            $remittance->paymentDocuments->each(function ($doc) {
                $doc->removeFromRemittance();
            });

            $remittance->update(['status' => 'CANCELLED']);

            DB::commit();

            return $remittance->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getRemittancesWithPendingDocuments(): array
    {
        $company = $this->getCompany();

        $pendingCheques = PaymentDocument::where('company_id', $company->id)
            ->where('type', 'cheque')
            ->whereIn('status', ['pending', 'returned'])
            ->whereNull('bank_remittance_id')
            ->with(['customer', 'payment.invoice.document'])
            ->orderBy('due_date')
            ->get();

        $pendingLcns = PaymentDocument::where('company_id', $company->id)
            ->where('type', 'lcn')
            ->whereIn('status', ['pending', 'returned'])
            ->whereNull('bank_remittance_id')
            ->with(['customer', 'payment.invoice.document'])
            ->orderBy('due_date')
            ->get();

        return [
            'cheques' => $pendingCheques,
            'lcn' => $pendingLcns,
            'total_amount' => $pendingCheques->sum('amount') + $pendingLcns->sum('amount'),
            'total_count' => $pendingCheques->count() + $pendingLcns->count(),
        ];
    }

    protected function getCompanyId(): int
    {
        return config('app.current_company_id')
            ?? throw new \RuntimeException('Aucune entreprise sélectionnée.');
    }

    protected function getCompany()
    {
        $company = \App\Models\Company::find($this->getCompanyId());

        if (!$company) {
            throw new \RuntimeException('Entreprise introuvable.');
        }

        return $company;
    }
}