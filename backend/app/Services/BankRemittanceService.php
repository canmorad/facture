<?php

namespace App\Services;

use App\Models\BankRemittance;
use App\Models\PaymentDocument;
use App\Models\Company;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BankRemittanceService
{
    public function __construct(
        protected NumberingService $numberingService
    ) {}

    public function getPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $companyId = $this->getCompanyId();
        $validStatuses = ['DRAFT', 'FINALIZED', 'SENT', 'DEPOSITED', 'RETURNED', 'CANCELLED'];

        $filters = array_merge([
            'status' => null,
            'search' => null,
            'date_from' => null,
            'date_to' => null,
            'bank_account_id' => null,
        ], $filters);

        $query = BankRemittance::where('company_id', $companyId)
            ->with(['bankAccount', 'paymentDocuments.customer.customerable']);

        if ($filters['status'] && in_array($filters['status'], $validStatuses)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['bank_account_id']) {
            $query->where('bank_account_id', $filters['bank_account_id']);
        }

        if ($filters['date_from']) {
            $query->where('remittance_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->where('remittance_date', '<=', $filters['date_to']);
        }

        if ($filters['search']) {
            $query->search($filters['search']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getCreationData(): array
    {
        $company = $this->getCompany();

        $bankAccounts = $company->bankAccounts()->where('is_active', true)->get();

        $pendingCheques = PaymentDocument::where('company_id', $company->id)
            ->where('type', 'cheque')
            ->where('status', 'pending')
            ->whereNull('bank_remittance_id')
            ->with('customer')
            ->get();

        $pendingLcns = PaymentDocument::where('company_id', $company->id)
            ->where('type', 'lcn')
            ->where('status', 'pending')
            ->whereNull('bank_remittance_id')
            ->with('customer')
            ->get();

        return [
            'bank_accounts' => $bankAccounts,
            'pending_cheques' => $pendingCheques,
            'pending_lcn' => $pendingLcns,
        ];
    }

    public function createDraft(array $validated): BankRemittance
    {
        $companyId = $this->getCompanyId();

        DB::beginTransaction();

        try {
            $remittance = BankRemittance::create([
                'company_id' => $companyId,
                'number' => null,
                'bank_account_id' => $validated['bank_account_id'],
                'status' => 'DRAFT',
                'remittance_date' => $validated['remittance_date'],
                'total_amount' => 0,
                'document_count' => 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            if (!empty($validated['payment_document_ids'])) {
                $this->attachPaymentDocuments($remittance, $validated['payment_document_ids']);
            }

            DB::commit();

            return $remittance->fresh(['bankAccount', 'paymentDocuments']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(int $id): BankRemittance
    {
        $companyId = $this->getCompanyId();

        return BankRemittance::where('id', $id)
            ->where('company_id', $companyId)
            ->with(['bankAccount', 'paymentDocuments.customer.customerable', 'paymentDocuments.document'])
            ->firstOrFail();
    }

    public function update(int $id, array $data): BankRemittance
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$remittance->canBeModified()) {
            throw new \InvalidArgumentException('Cette remise ne peut plus être modifiée.');
        }

        DB::beginTransaction();

        try {
            $updateData = [
                'bank_account_id' => $data['bank_account_id'] ?? $remittance->bank_account_id,
                'remittance_date' => $data['remittance_date'] ?? $remittance->remittance_date,
                'notes' => $data['notes'] ?? $remittance->notes,
            ];

            $remittance->update($updateData);

            if (isset($data['payment_document_ids'])) {
                $currentDocIds = $remittance->paymentDocuments()->pluck('id')->toArray();
                $newDocIds = $data['payment_document_ids'];

                $docsToRemove = array_diff($currentDocIds, $newDocIds);
                $docsToAdd = array_diff($newDocIds, $currentDocIds);

                foreach ($docsToRemove as $docId) {
                    $remittance->removePaymentDocument($docId);
                }

                if (!empty($docsToAdd)) {
                    $this->attachPaymentDocuments($remittance, $docsToAdd);
                }
            }

            DB::commit();

            return $remittance->fresh(['bankAccount', 'paymentDocuments']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function attachPaymentDocuments(BankRemittance $remittance, array $documentIds): void
    {
        foreach ($documentIds as $docId) {
            $remittance->addPaymentDocument($docId);
        }
    }

    public function removePaymentDocument(int $remittanceId, int $documentId): BankRemittance
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $remittanceId)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$remittance->removePaymentDocument($documentId)) {
            throw new \RuntimeException('Impossible de supprimer ce document de la remise.');
        }

        return $remittance->fresh(['bankAccount', 'paymentDocuments']);
    }

    public function finalize(int $id): BankRemittance
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$remittance->canBeFinalized()) {
            throw new \InvalidArgumentException('Cette remise ne peut pas être finalisée.');
        }

        $number = $this->numberingService->generateNumber('bank_remittance', $companyId);

        $remittance->finalize($number);

        return $remittance->fresh(['bankAccount', 'paymentDocuments']);
    }

    public function send(int $id): BankRemittance
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$remittance->canBeSent()) {
            throw new \InvalidArgumentException('Cette remise ne peut pas être envoyée.');
        }

        $remittance->markAsSent();

        return $remittance->fresh(['bankAccount', 'paymentDocuments']);
    }

    public function markDeposited(int $id, ?string $depositSlipRef = null): BankRemittance
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$remittance->canBeDeposited()) {
            throw new \InvalidArgumentException('Cette remise ne peut pas être marquée comme déposée.');
        }

        $remittance->markAsDeposited($depositSlipRef);

        return $remittance->fresh(['bankAccount', 'paymentDocuments']);
    }

    public function cancel(int $id): BankRemittance
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$remittance->canBeCancelled()) {
            throw new \InvalidArgumentException('Cette remise ne peut pas être annulée.');
        }

        $remittance->markAsCancelled();

        return $remittance->fresh(['bankAccount', 'paymentDocuments']);
    }

    public function delete(int $id): void
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        if (!$remittance->canBeModified()) {
            throw new \InvalidArgumentException('Seules les remises en brouillon peuvent être supprimées.');
        }

        $remittance->paymentDocuments()->each(function ($doc) {
            $doc->removeFromRemittance();
        });

        $remittance->delete();
    }

    public function getAvailableActions(int $id): array
    {
        $companyId = $this->getCompanyId();

        $remittance = BankRemittance::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        return [
            'can_edit' => $remittance->canBeModified(),
            'can_finalize' => $remittance->canBeFinalized(),
            'can_send' => $remittance->canBeSent(),
            'can_deposit' => $remittance->canBeDeposited(),
            'can_cancel' => $remittance->canBeCancelled(),
            'can_delete' => $remittance->canBeModified(),
        ];
    }

    public function getPendingPaymentDocuments(): array
    {
        $company = $this->getCompany();

        $pendingCheques = PaymentDocument::where('company_id', $company->id)
            ->where('type', 'cheque')
            ->whereIn('status', ['pending', 'returned'])
            ->whereNull('bank_remittance_id')
            ->with('customer')
            ->orderBy('due_date')
            ->get();

        $pendingLcns = PaymentDocument::where('company_id', $company->id)
            ->where('type', 'lcn')
            ->whereIn('status', ['pending', 'returned'])
            ->whereNull('bank_remittance_id')
            ->with('customer')
            ->orderBy('due_date')
            ->get();

        return [
            'cheques' => $pendingCheques,
            'lcn' => $pendingLcns,
        ];
    }

    protected function getCompanyId(): int
    {
        return config('app.current_company_id') ?? throw new \RuntimeException('Aucune entreprise sélectionnée.');
    }

    protected function getCompany(): Company
    {
        $company = Company::find($this->getCompanyId());

        if (!$company) {
            throw new \RuntimeException('Entreprise introuvable.');
        }

        return $company;
    }
}
