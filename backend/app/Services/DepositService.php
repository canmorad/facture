<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Deposit;
use App\Models\Quote;
use App\Models\Company;
use App\Exceptions\DepositLimitExceededException;
use Illuminate\Support\Facades\DB;

class DepositService
{
    protected ?int $companyId = null;

    public function __construct(
        protected DocumentCalculationService $calculationService,
        protected NumberingService $numberingService,
        protected DocumentService $documentService,
        protected DocumentItemService $documentItemService
    ) {}

    public function setCompanyId(?int $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function getAll(?string $status = null)
    {
        $companyId = $this->getCompanyId();

        $query = Document::where('company_id', $companyId)
            ->where('documentable_type', Deposit::class)
            ->with(['customer', 'items', 'documentable']);

        if ($status && in_array($status, ['DRAFT', 'FINALIZED', 'PAID', 'CANCELLED'])) {
            $query->whereHas('documentable', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getCreationData(): array
    {
        $company = $this->getCompany();

        $quotes = $company->quotes()
            ->whereHas('document', function ($q) {
                $q->whereNotNull('number');
            })
            ->with('document')
            ->get();

        $taxRates = $company->taxRates()->where('is_actif', true)->get();
        $bankAccounts = $company->bankAccounts()->where('is_active', true)->get();

        $paymentConditions = $company->paymentConditions()->where('is_active', true)->get();
        $paymentModes = $company->paymentModes()->where('is_active', true)->get();
        $lateFeeInterests = $company->lateFeeInterests()->where('is_active', true)->get();

        $settings = $company->documentSettings()->where('document_type', 'DEPOSIT')->first();

        $defaults = [
            'intro_text' => $settings->intro_text ?? '',
            'footer_text' => $settings->footer_text ?? '',
            'terms' => $settings->terms ?? '',
            'conclusion_text' => $settings->conclusion_text ?? '',
        ];

        return [
            'quotes' => $quotes,
            'tax_rates' => $taxRates,
            'bank_accounts' => $bankAccounts,
            'payment_conditions' => $paymentConditions,
            'payment_modes' => $paymentModes,
            'late_fee_interests' => $lateFeeInterests,
            'defaults' => $defaults,
        ];
    }

    public function getRemainingBalance(int $quoteId): array
    {
        $companyId = $this->getCompanyId();

        $quote = Quote::with('document')->findOrFail($quoteId);

        if ($quote->document->company_id !== $companyId) {
            throw new \RuntimeException('Quote non autorisé.');
        }

        $quoteTotalTtc = $quote->document->total_ttc;

        $activeDeposits = Deposit::where('quote_id', $quoteId)
            ->whereIn('status', ['FINALIZED', 'PAID'])
            ->with('document')
            ->get();

        $depositedTotalTtc = $activeDeposits->sum(function ($deposit) {
            return $deposit->document ? $deposit->document->total_ttc : 0;
        });

        $remainingBalance = max(0, $quoteTotalTtc - $depositedTotalTtc);

        return [
            'quote_total_ttc' => $quoteTotalTtc,
            'deposited_total_ttc' => $depositedTotalTtc,
            'remaining_balance' => $remainingBalance,
        ];
    }

    public function createDeposit(array $validated): Document
    {
        $companyId = $this->getCompanyId();

        $quote = Quote::with('document')->findOrFail($validated['quote_id']);
        if ($quote->document->company_id !== $companyId) {
            throw new \RuntimeException('Quote non autorisé.');
        }

        $balance = $this->getRemainingBalance($validated['quote_id']);

        $inputType = $validated['input_type'];
        $inputValue = $validated['input_value'];
        $taxRate = $validated['tax_rate'];

        if ($inputType === 'percentage') {
            $depositTtc = $balance['quote_total_ttc'] * ($inputValue / 100);
        } else {
            $depositTtc = $inputValue;
        }

        if ($depositTtc > $balance['remaining_balance']) {
            throw new DepositLimitExceededException();
        }

        $totalHt = $depositTtc / (1 + ($taxRate / 100));
        $totalTva = $depositTtc - $totalHt;

        return DB::transaction(function () use ($validated, $companyId, $totalHt, $totalTva, $depositTtc, $taxRate, $inputType, $inputValue, $quote) {
            $number = $this->numberingService->generateNumber('deposit_invoice', $companyId);

            $document = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $quote->document->customer_id,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'number' => $number,
                'total_ht' => $totalHt,
                'total_tva' => $totalTva,
                'total_ttc' => $depositTtc,
                'global_discount_type' => null,
                'global_discount_value' => 0,
                'global_discount_amount' => 0,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'intro_text' => $validated['intro_text'] ?? null,
                'footer_text' => $validated['footer_text'] ?? null,
                'conclusion_text' => $validated['conclusion_text'] ?? null,
                'documentable_type' => Deposit::class,
                'documentable_id' => 0,
                'payment_condition' => $validated['payment_condition'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? null,
                'late_fee_interest' => $validated['late_fee_interest'] ?? null,
            ]);

            $deposit = Deposit::create([
                'company_id' => $companyId,
                'quote_id' => $validated['quote_id'],
                'status' => 'PAID',
                'input_type' => $inputType,
                'input_value' => $inputValue,
            ]);

            $document->documentable_id = $deposit->id;
            $document->save();

            $description = $validated['deposit_description'] ?? 'Facture d\'acompte';

            $this->documentItemService->createMany($document->id, [
                [
                    'product_id' => null,
                    'product_type' => null,
                    'designation' => $description,
                    'quantity' => 1,
                    'unit_price' => $totalHt,
                    'tax_rate' => $taxRate,
                    'discount_type' => null,
                    'discount_value' => 0,
                    'calculated_ht' => $totalHt,
                    'calculated_ttc' => $depositTtc,
                ]
            ]);

            return $document->load('customer', 'items');
        });
    }

    public function finalize(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Deposit::class) {
            throw new \InvalidArgumentException('Document is not a Deposit.');
        }

        $companyId = $this->getCompanyId();

        return DB::transaction(function () use ($document, $companyId) {
            $number = $this->numberingService->generateNumber('deposit_invoice', $companyId);

            $this->documentService->updateNumber($document, $number);

            $deposit = $document->documentable;
            $deposit->status = 'PAID';
            $deposit->save();

            return $document->fresh(['customer', 'items', 'documentable']);
        });
    }

    protected function getCompanyId(): int
    {
        if (!$this->companyId) {
            throw new \RuntimeException('Aucune entreprise sélectionnée.');
        }

        return $this->companyId;
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