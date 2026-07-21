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
    public function __construct(
        protected DocumentCalculationService $calculationService,
        protected NumberingService $numberingService,
        protected DocumentService $documentService,
        protected DocumentItemService $documentItemService,
        protected FiscalComplianceService $fiscalService
    ) {}

    public function getPaginated(array $filters = [], int $perPage = 10)
    {
        $companyId = $this->getCompanyId();
        $validStatuses = ['DRAFT', 'FINALIZED', 'PAID', 'CANCELLED'];

        $filters = array_merge([
            'status' => null,
            'search' => null,
            'date_from' => null,
            'date_to' => null,
            'customer_id' => null,
        ], $filters);

        $query = Document::where('company_id', $companyId)
            ->where('documentable_type', Deposit::class)
            ->with(['customer.customerable', 'items.product', 'items.product.category', 'documentable', 'parent']);

        if ($filters['status'] && in_array($filters['status'], $validStatuses)) {
            $query->whereHas('documentable', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
        }

        if ($filters['customer_id']) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if ($filters['date_from']) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        if ($filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    })
                    ->orWhere('total_ht', 'like', "%{$search}%")
                    ->orWhere('total_ttc', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getAll(?string $status = null)
    {
        $companyId = $this->getCompanyId();

        $query = Document::where('company_id', $companyId)
            ->where('documentable_type', Deposit::class)
            ->with(['customer', 'items', 'documentable.quote.document']);

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
            ->with('document.customer.customerable')
            ->get();

        $customers = $company->customers()->with('customerable')->get();
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
            'customers' => $customers,
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

        $activeDepositIds = Deposit::where('quote_id', $quoteId)
            ->whereIn('status', ['FINALIZED', 'PAID'])
            ->pluck('id');

        $depositedTotalTtc = Document::where('documentable_type', Deposit::class)
            ->whereIn('documentable_id', $activeDepositIds)
            ->where('company_id', $companyId)
            ->sum('total_ttc');

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

        // Determine if we're creating from a quote or standalone
        $quoteId = $validated['quote_id'] ?? null;
        $customerId = $validated['customer_id'] ?? null;

        if ($quoteId) {
            // Creation from quote - validate and get quote data
            $quote = Quote::with('document')->findOrFail($quoteId);
            if ($quote->document->company_id !== $companyId) {
                throw new \RuntimeException('Quote non autorisé.');
            }

            $customerId = $quote->document->customer_id;
            $parentDocumentId = $quote->document->id;

            $balance = $this->getRemainingBalance($quoteId);
        } else {
            // Standalone creation - customer_id is required
            if (!$customerId) {
                throw new \RuntimeException('Customer ID est requis pour créer un acompte sans devis.');
            }

            $parentDocumentId = null;
            $balance = null;
        }

        $inputType = $validated['input_type'];
        $inputValue = $validated['input_value'];
        $taxRate = $validated['tax_rate'];

        // Calculate deposit amount
        if ($quoteId) {
            // Linked to quote - validate against remaining balance
            if ($inputType === 'percentage') {
                $depositTtc = $balance['quote_total_ttc'] * ($inputValue / 100);
            } else {
                $depositTtc = $inputValue;
            }

            if ($depositTtc > $balance['remaining_balance']) {
                throw new DepositLimitExceededException();
            }
        } else {
            // Standalone - use the input value directly
            if ($inputType === 'percentage') {
                throw new \RuntimeException('Le pourcentage n\'est pas supporté pour un acompte sans devis.');
            }
            $depositTtc = $inputValue;

            if ($depositTtc <= 0) {
                throw new \RuntimeException('Le montant de l\'acompte doit être supérieur à zéro.');
            }
        }

        $totalHt = $depositTtc / (1 + ($taxRate / 100));
        $totalTva = $depositTtc - $totalHt;

        return DB::transaction(function () use ($validated, $companyId, $totalHt, $totalTva, $depositTtc, $taxRate, $inputType, $inputValue, $customerId, $parentDocumentId, $quoteId) {
            // RÈGLE COMPTABLE STRICTE : Le champ number reste NULL pour les brouillons
            // Aucun appel au service de numérotation lors de la création

            $document = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'parent_document_id' => $parentDocumentId,
                'number' => null,  // NULL strict pour les brouillons
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
                'quote_id' => $quoteId,
                'status' => 'DRAFT',
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
            $deposit->status = 'FINALIZED';
            $deposit->save();

            return $document->fresh(['customer', 'items', 'documentable']);
        });
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