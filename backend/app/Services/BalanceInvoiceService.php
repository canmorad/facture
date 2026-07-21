<?php

namespace App\Services;

use App\Models\Document;
use App\Models\BalanceInvoice;
use App\Models\Quote;
use App\Models\Deposit;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class BalanceInvoiceService
{
    public function __construct(
        protected DocumentCalculationService $calculationService,
        protected NumberingService $numberingService,
        protected DocumentService $documentService,
        protected DocumentItemService $documentItemService
    ) {}

    public function getPaginated(array $filters = [], int $perPage = 10)
    {
        $companyId = $this->getCompanyId();
        $validStatuses = ['DRAFT', 'FINALIZED', 'SENT', 'PAID', 'CANCELLED'];

        $filters = array_merge([
            'status' => null,
            'search' => null,
            'date_from' => null,
            'date_to' => null,
            'customer_id' => null,
        ], $filters);

        $query = Document::where('company_id', $companyId)
            ->where('documentable_type', BalanceInvoice::class)
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

    public function getCreationData(?int $quoteId = null): array
    {
        $company = $this->getCompany();

        $quotesQuery = $company->quotes()->with('document.customer.customerable');

        // Only get signed quotes
        $quotesQuery->where('status', 'SIGNED');

        $quotes = $quotesQuery->get();

        $taxRates = $company->taxRates()->where('is_actif', true)->get();
        $bankAccounts = $company->bankAccounts()->where('is_active', true)->get();

        $paymentConditions = $company->paymentConditions()->where('is_active', true)->get();
        $paymentModes = $company->paymentModes()->where('is_active', true)->get();
        $lateFeeInterests = $company->lateFeeInterests()->where('is_active', true)->get();

        $settings = $company->documentSettings()->where('document_type', 'BALANCE_INVOICE')->first();

        $defaults = [
            'intro_text' => $settings->intro_text ?? '',
            'footer_text' => $settings->footer_text ?? '',
            'terms' => $settings->terms ?? '',
            'conclusion_text' => $settings->conclusion_text ?? '',
        ];

        $balanceData = null;
        $selectedQuote = null;

        if ($quoteId) {
            $selectedQuote = Quote::with('document')->findOrFail($quoteId);
            if ($selectedQuote->document->company_id !== $company->id) {
                throw new \RuntimeException('Devis non autorisé.');
            }
            $balanceData = $this->getBalanceData($quoteId);
        }

        return [
            'quotes' => $quotes,
            'tax_rates' => $taxRates,
            'bank_accounts' => $bankAccounts,
            'payment_conditions' => $paymentConditions,
            'payment_modes' => $paymentModes,
            'late_fee_interests' => $lateFeeInterests,
            'defaults' => $defaults,
            'balance_data' => $balanceData,
            'selected_quote' => $selectedQuote ? [
                'id' => $selectedQuote->id,
                'number' => $selectedQuote->document->number,
                'total_ttc' => $selectedQuote->document->total_ttc,
            ] : null,
        ];
    }

    public function getBalanceData(int $quoteId): array
    {
        $companyId = $this->getCompanyId();

        $quote = Quote::with('document')->findOrFail($quoteId);

        if ($quote->document->company_id !== $companyId) {
            throw new \RuntimeException('Devis non autorisé.');
        }

        $quoteTotalTtc = $quote->document->total_ttc;

        // Get all deposits for this quote
        $deposits = Deposit::where('quote_id', $quoteId)
            ->whereIn('status', ['FINALIZED', 'PAID'])
            ->get();

        $depositedTotalTtc = 0;
        $depositIds = [];
        $depositDetails = [];

        foreach ($deposits as $deposit) {
            $depositDoc = Document::where('documentable_type', Deposit::class)
                ->where('documentable_id', $deposit->id)
                ->first();

            if ($depositDoc) {
                $depositedTotalTtc += $depositDoc->total_ttc;
                $depositIds[] = $deposit->id;
                $depositDetails[] = [
                    'id' => $deposit->id,
                    'number' => $depositDoc->number,
                    'amount' => $depositDoc->total_ttc,
                    'status' => $deposit->status,
                ];
            }
        }

        // Check for existing balance invoices
        $existingBalanceInvoices = BalanceInvoice::where('quote_id', $quoteId)
            ->whereIn('status', ['FINALIZED', 'SENT', 'PAID'])
            ->get();

        $balanceInvoicedTotalTtc = 0;
        foreach ($existingBalanceInvoices as $bi) {
            $biDoc = Document::where('documentable_type', BalanceInvoice::class)
                ->where('documentable_id', $bi->id)
                ->first();
            if ($biDoc) {
                $balanceInvoicedTotalTtc += $biDoc->total_ttc;
            }
        }

        $totalDeducted = $depositedTotalTtc + $balanceInvoicedTotalTtc;
        $remainingBalance = max(0, $quoteTotalTtc - $totalDeducted);

        return [
            'quote_id' => $quoteId,
            'quote_number' => $quote->document->number,
            'quote_total_ttc' => $quoteTotalTtc,
            'deposited_total_ttc' => $depositedTotalTtc,
            'balance_invoiced_total_ttc' => $balanceInvoicedTotalTtc,
            'total_deducted' => $totalDeducted,
            'remaining_balance' => $remainingBalance,
            'deposit_ids' => $depositIds,
            'deposit_details' => $depositDetails,
            'can_create_balance' => $remainingBalance > 0,
        ];
    }

    public function createBalanceInvoice(array $validated): Document
    {
        $companyId = $this->getCompanyId();

        $quote = Quote::with('document')->findOrFail($validated['quote_id']);
        if ($quote->document->company_id !== $companyId) {
            throw new \RuntimeException('Devis non autorisé.');
        }

        $balanceData = $this->getBalanceData($validated['quote_id']);

        if (!$balanceData['can_create_balance']) {
            throw new \RuntimeException('Le solde restant est insuffisant pour créer une facture de solde.');
        }

        // Calculate the balance invoice amount
        $inputType = $validated['input_type'] ?? 'full';
        $inputValue = $validated['input_value'] ?? 0;
        $taxRate = $validated['tax_rate'] ?? 20;

        if ($inputType === 'percentage') {
            $balanceTtc = $balanceData['remaining_balance'] * ($inputValue / 100);
        } elseif ($inputType === 'fixed') {
            $balanceTtc = min($inputValue, $balanceData['remaining_balance']);
        } else {
            // Default: full remaining balance
            $balanceTtc = $balanceData['remaining_balance'];
        }

        if ($balanceTtc <= 0) {
            throw new \RuntimeException('Le montant de la facture de solde doit être supérieur à zéro.');
        }

        $totalHt = $balanceTtc / (1 + ($taxRate / 100));
        $totalTva = $balanceTtc - $totalHt;

        return DB::transaction(function () use ($validated, $companyId, $totalHt, $totalTva, $balanceTtc, $taxRate, $quote, $balanceData) {
            // RÈGLE COMPTABLE STRICTE : Le champ number reste NULL pour les brouillons
            // Aucun appel au service de numérotation lors de la création

            $document = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $quote->document->customer_id,
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'parent_document_id' => $quote->document->id,
                'number' => null,  // NULL strict pour les brouillons
                'total_ht' => $totalHt,
                'total_tva' => $totalTva,
                'total_ttc' => $balanceTtc,
                'global_discount_type' => null,
                'global_discount_value' => 0,
                'global_discount_amount' => 0,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'intro_text' => $validated['intro_text'] ?? null,
                'footer_text' => $validated['footer_text'] ?? null,
                'conclusion_text' => $validated['conclusion_text'] ?? null,
                'documentable_type' => BalanceInvoice::class,
                'documentable_id' => 0,
                'payment_condition' => $validated['payment_condition'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? null,
                'late_fee_interest' => $validated['late_fee_interest'] ?? null,
            ]);

            $balanceInvoice = BalanceInvoice::create([
                'company_id' => $companyId,
                'quote_id' => $validated['quote_id'],
                'status' => $validated['status'] ?? 'DRAFT',
                'input_type' => $validated['input_type'] ?? 'full',
                'input_value' => $validated['input_value'] ?? $balanceTtc,
                'deposit_ids' => $balanceData['deposit_ids'] ?? [],
                'calculated_balance' => $balanceTtc,
            ]);

            $document->documentable_id = $balanceInvoice->id;
            $document->save();

            $description = $validated['balance_description'] ?? 'Facture de solde';

            // Copy items from quote or create a single line item
            $quoteItems = $quote->document->items;

            if ($quoteItems->isEmpty()) {
                // Create single line item
                $this->documentItemService->createMany($document->id, [
                    [
                        'product_id' => null,
                        'product_type' => null,
                        'designation' => $description ?: 'Facture de solde',
                        'quantity' => 1,
                        'unit_price' => $totalHt,
                        'tax_rate' => $taxRate,
                        'discount_type' => null,
                        'discount_value' => 0,
                        'calculated_ht' => $totalHt,
                        'calculated_ttc' => $balanceTtc,
                    ]
                ]);
            } else {
                // Copy quote items proportionally
                $quoteTotalHt = $quote->document->total_ht;
                $ratio = $quoteTotalHt > 0 ? $totalHt / $quoteTotalHt : 0;

                $items = $quoteItems->map(function ($item) use ($ratio, $taxRate, $description) {
                    $calculatedHt = $item->calculated_ht * $ratio;
                    $calculatedTtc = $calculatedHt * (1 + ($taxRate / 100));

                    return [
                        'product_id' => $item->product_id,
                        'product_type' => $item->product_type,
                        'designation' => $item->designation ?: 'Article',
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'tax_rate' => $taxRate,
                        'discount_type' => $item->discount_type,
                        'discount_value' => $item->discount_value ?? 0,
                        'calculated_ht' => $calculatedHt,
                        'calculated_ttc' => $calculatedTtc,
                    ];
                })->toArray();

                $this->documentItemService->createMany($document->id, $items);
            }

            return $document->load('customer', 'items', 'documentable');
        });
    }

    public function finalize(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== BalanceInvoice::class) {
            throw new \InvalidArgumentException('Document is not a Balance Invoice.');
        }

        $companyId = $this->getCompanyId();

        return DB::transaction(function () use ($document, $companyId) {
            $number = $this->numberingService->generateNumber('balance_invoice', $companyId);

            $this->documentService->updateNumber($document, $number);

            $balanceInvoice = $document->documentable;
            $balanceInvoice->status = 'FINALIZED';
            $balanceInvoice->save();

            return $document->fresh(['customer', 'items', 'documentable']);
        });
    }

    public function updateStatus(int $id, string $status): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== BalanceInvoice::class) {
            throw new \InvalidArgumentException('Document is not a Balance Invoice.');
        }

        $balanceInvoice = $document->documentable;
        $balanceInvoice->status = $status;
        $balanceInvoice->save();

        return $document->fresh(['customer', 'items', 'documentable']);
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
