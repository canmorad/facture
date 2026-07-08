<?php

namespace App\Services;

use App\Exceptions\ImmutableDocumentException;
use App\Models\Document;
use App\Models\Quote;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\DeliveryNote;
use App\Models\Deposit;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class QuoteService
{
    public function __construct(
        protected DocumentCalculationService $calculationService,
        protected NumberingService $numberingService,
        protected DocumentService $documentService,
        protected DocumentItemService $documentItemService
    ) {}

    public function getAll(?string $status = null)
    {
        $companyId = $this->getCompanyId();

        $query = Document::where('company_id', $companyId)
            ->where('documentable_type', Quote::class)
            ->with(['customer', 'items', 'documentable', 'parent']);

        if ($status && in_array($status, ['DRAFT', 'FINALIZED', 'SENT', 'SIGNED', 'EXPIRED'])) {
            $query->whereHas('documentable', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getCreationData(): array
    {
        $company = $this->getCompany();

        $products = $company->products;
        $taxRates = $company->taxRates()->where('is_actif', true)->get();
        $productTypes = $company->productCategories()->where('is_active', true)->get();
        $bankAccounts = $company->bankAccounts()->where('is_active', true)->get();
        $customers = $company->customers()->where('is_active', true)->with('customerable')->get();

        $paymentConditions = $company->paymentConditions()->where('is_active', true)->get();
        $paymentModes = $company->paymentModes()->where('is_active', true)->get();
        $lateFeeInterests = $company->lateFeeInterests()->where('is_active', true)->get();

        $settings = $company->documentSettings()->where('document_type', 'QUOTE')->first();

        $defaults = [
            'intro_text' => $settings->intro_text ?? '',
            'footer_text' => $settings->footer_text ?? '',
            'terms' => $settings->terms ?? '',
            'conclusion_text' => $settings->conclusion_text ?? '',
        ];

        return [
            'products' => $products,
            'tax_rates' => $taxRates,
            'product_types' => $productTypes,
            'bank_accounts' => $bankAccounts,
            'customers' => $customers,
            'payment_conditions' => $paymentConditions,
            'payment_modes' => $paymentModes,
            'late_fee_interests' => $lateFeeInterests,
            'defaults' => $defaults,
        ];
    }

    public function createDraft(array $validated): Document
    {
        $companyId = $this->getCompanyId();

        $calculated = $this->calculationService->calculate(
            $validated['items'],
            $validated['global_discount_type'] ?? null,
            $validated['global_discount_value'] ?? null
        );

        DB::beginTransaction();

        try {
            $document = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $validated['customer_id'],
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'parent_document_id' => $validated['parent_document_id'] ?? null,
                'number' => null,
                'total_ht' => $calculated['total_ht'],
                'total_tva' => $calculated['total_tva'],
                'total_ttc' => $calculated['total_ttc'],
                'global_discount_type' => $validated['global_discount_type'] ?? null,
                'global_discount_value' => $validated['global_discount_value'] ?? 0,
                'global_discount_amount' => $calculated['global_discount_amount'],
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'intro_text' => $validated['intro_text'] ?? null,
                'footer_text' => $validated['footer_text'] ?? null,
                'conclusion_text' => $validated['conclusion_text'] ?? null,
                'documentable_type' => Quote::class,
                'documentable_id' => 0,
                'payment_condition' => $validated['payment_condition'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? null,
                'late_fee_interest' => $validated['late_fee_interest'] ?? null,
            ]);

            $quote = Quote::create([
                'status' => 'DRAFT',
                'valid_until' => $validated['valid_until'] ?? null,
            ]);

            $document->documentable_id = $quote->id;
            $document->save();

            $itemsWithTotals = [];
            foreach ($validated['items'] as $idx => $item) {
                $processed = $calculated['processed_items'][$idx];
                $lineHt = $processed['line_ht'];
                $lineTtc = $lineHt * (1 + $item['tax_rate'] / 100);
                $itemsWithTotals[] = array_merge($item, [
                    'calculated_ht' => $lineHt,
                    'calculated_ttc' => $lineTtc,
                ]);
            }

            $this->documentItemService->createMany($document->id, $itemsWithTotals);

            DB::commit();

            return $document->load('customer', 'items', 'documentable');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function finalize(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Quote::class) {
            throw new \InvalidArgumentException('Le document n\'est pas un devis.');
        }

        $companyId = $this->getCompanyId();
        $number = $this->numberingService->generateNumber('quote', $companyId);

        DB::beginTransaction();

        try {
            $this->documentService->updateNumber($document, $number);

            $quote = $document->documentable;
            $quote->transitionTo('FINALIZED');

            DB::commit();

            return $document->fresh(['customer', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function send(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Quote::class) {
            throw new \InvalidArgumentException('Le document n\'est pas un devis.');
        }

        DB::beginTransaction();

        try {
            $quote = $document->documentable;
            $quote->transitionTo('SENT');

            DB::commit();

            return $document->fresh(['customer', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function sign(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Quote::class) {
            throw new \InvalidArgumentException('Le document n\'est pas un devis.');
        }

        DB::beginTransaction();

        try {
            $quote = $document->documentable;
            $quote->transitionTo('SIGNED');

            DB::commit();

            return $document->fresh(['customer', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function convertToInvoice(int $quoteId, ?string $invoiceType = 'STANDARD'): Document
    {
        $sourceDocument = Document::with(['documentable', 'items', 'customer', 'children'])
            ->where('documentable_type', Quote::class)
            ->whereHas('documentable', function ($q) {
                $q->where('status', 'SIGNED');
            })
            ->findOrFail($quoteId);

        if ($invoiceType === 'SOLDE') {
            return $this->convertToSoldeInvoice($quoteId);
        }

        $existingInvoice = $sourceDocument->children()
            ->where('documentable_type', Invoice::class)
            ->whereHasMorph('documentable', [Invoice::class], function ($q) {
                $q->whereIn('type', ['STANDARD', 'SOLDE']);
            })
            ->exists();

        if ($existingInvoice) {
            throw new \RuntimeException('Ce devis a déjà une facture standard ou de solde.');
        }

        $companyId = $this->getCompanyId();

        $number = $this->numberingService->generateNumber('invoice', $companyId);

        DB::beginTransaction();

        try {
            $invoice = Invoice::create([
                'status' => 'DRAFT',
                'due_date' => null,
                'type' => 'STANDARD',
            ]);

            $newDocument = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $sourceDocument->customer_id,
                'bank_account_id' => $sourceDocument->bank_account_id,
                'parent_document_id' => $sourceDocument->id,
                'number' => $number,
                'total_ht' => $sourceDocument->total_ht,
                'total_tva' => $sourceDocument->total_tva,
                'total_ttc' => $sourceDocument->total_ttc,
                'global_discount_type' => $sourceDocument->global_discount_type,
                'global_discount_value' => $sourceDocument->global_discount_value,
                'global_discount_amount' => $sourceDocument->global_discount_amount,
                'notes' => $sourceDocument->notes,
                'terms' => $sourceDocument->terms,
                'intro_text' => $sourceDocument->intro_text,
                'footer_text' => $sourceDocument->footer_text,
                'conclusion_text' => $sourceDocument->conclusion_text,
                'documentable_type' => Invoice::class,
                'documentable_id' => $invoice->id,
                'payment_condition' => $sourceDocument->payment_condition,
                'payment_mode' => $sourceDocument->payment_mode,
                'late_fee_interest' => $sourceDocument->late_fee_interest,
            ]);

            foreach ($sourceDocument->items as $item) {
                $newDocument->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'product_type' => $item->product_type,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'total_ht' => $item->total_ht,
                    'total_ttc' => $item->total_ttc,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                ]);
            }

            $newDocument->finalized_at = now();
            $newDocument->save();

            $invoice->transitionTo('FINALIZED');

            DB::commit();

            return $newDocument->load('customer', 'items', 'documentable', 'parent');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function convertToSoldeInvoice(int $quoteId): Document
    {
        $sourceDocument = Document::with(['documentable', 'items', 'customer', 'children'])
            ->where('documentable_type', Quote::class)
            ->whereHas('documentable', function ($q) {
                $q->where('status', 'SIGNED');
            })
            ->findOrFail($quoteId);

        $existingSoldeInvoice = $sourceDocument->children()
            ->where('documentable_type', Invoice::class)
            ->whereHasMorph('documentable', [Invoice::class], function ($q) {
                $q->where('type', 'SOLDE');
            })
            ->exists();

        if ($existingSoldeInvoice) {
            throw new \RuntimeException('Ce devis a déjà une facture de solde.');
        }

        $paidDepositsTotal = Deposit::where('quote_id', $quoteId)
            ->where('status', 'PAID')
            ->sum('input_value');

        $quoteTotalTtc = $sourceDocument->total_ttc;
        $remainingBalance = $quoteTotalTtc - $paidDepositsTotal;

        if ($remainingBalance <= 0) {
            throw new \RuntimeException('Aucun solde restant à facturer. Le total des acomptes payés (' . $paidDepositsTotal . ') est supérieur ou égal au total du devis (' . $quoteTotalTtc . ').');
        }

        $companyId = $this->getCompanyId();

        $number = $this->numberingService->generateNumber('invoice', $companyId);

        DB::beginTransaction();

        try {
            $invoice = Invoice::create([
                'status' => 'DRAFT',
                'due_date' => null,
                'type' => 'SOLDE',
            ]);

            $newDocument = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $sourceDocument->customer_id,
                'bank_account_id' => $sourceDocument->bank_account_id,
                'parent_document_id' => $sourceDocument->id,
                'number' => $number,
                'total_ht' => $remainingBalance,
                'total_tva' => 0,
                'total_ttc' => $remainingBalance,
                'global_discount_type' => null,
                'global_discount_value' => 0,
                'global_discount_amount' => 0,
                'notes' => 'Facture de solde - Acomptes déjà réglés : ' . number_format($paidDepositsTotal, 2) . ' DH',
                'terms' => $sourceDocument->terms,
                'intro_text' => $sourceDocument->intro_text,
                'footer_text' => $sourceDocument->footer_text,
                'conclusion_text' => $sourceDocument->conclusion_text,
                'documentable_type' => Invoice::class,
                'documentable_id' => $invoice->id,
                'payment_condition' => $sourceDocument->payment_condition,
                'payment_mode' => $sourceDocument->payment_mode,
                'late_fee_interest' => $sourceDocument->late_fee_interest,
            ]);

            $newDocument->items()->create([
                'product_id' => null,
                'description' => 'Solde à payer (Total TTC: ' . number_format($quoteTotalTtc, 2) . ' DH - Acomptes payés: ' . number_format($paidDepositsTotal, 2) . ' DH)',
                'product_type' => null,
                'quantity' => 1,
                'unit_price' => $remainingBalance,
                'tax_rate' => 0,
                'total_ht' => $remainingBalance,
                'total_ttc' => $remainingBalance,
                'discount_type' => null,
                'discount_value' => 0,
            ]);

            DB::commit();

            return $newDocument->load('customer', 'items', 'documentable', 'parent');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function convertToPurchaseOrder(int $quoteId): Document
    {
        $sourceDocument = Document::with('documentable', 'items', 'customer')
            ->where('documentable_type', Quote::class)
            ->whereHas('documentable', function ($q) {
                $q->where('status', 'SIGNED');
            })
            ->findOrFail($quoteId);

        $existingPO = Document::where('parent_document_id', $sourceDocument->id)
            ->where('documentable_type', PurchaseOrder::class)
            ->exists();

        if ($existingPO) {
            throw new \RuntimeException('Ce devis a déjà un bon de commande.');
        }

        $companyId = $this->getCompanyId();

        $number = $this->numberingService->generateNumber('purchase_order', $companyId);

        DB::beginTransaction();

        try {
            $po = PurchaseOrder::create([
                'status' => 'DRAFT',
                'expected_date' => null,
            ]);

            $newDocument = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $sourceDocument->customer_id,
                'bank_account_id' => $sourceDocument->bank_account_id,
                'parent_document_id' => $sourceDocument->id,
                'number' => $number,
                'total_ht' => $sourceDocument->total_ht,
                'total_tva' => $sourceDocument->total_tva,
                'total_ttc' => $sourceDocument->total_ttc,
                'global_discount_type' => $sourceDocument->global_discount_type,
                'global_discount_value' => $sourceDocument->global_discount_value,
                'global_discount_amount' => $sourceDocument->global_discount_amount,
                'notes' => $sourceDocument->notes,
                'terms' => $sourceDocument->terms,
                'intro_text' => $sourceDocument->intro_text,
                'footer_text' => $sourceDocument->footer_text,
                'conclusion_text' => $sourceDocument->conclusion_text,
                'documentable_type' => PurchaseOrder::class,
                'documentable_id' => $po->id,
                'payment_condition' => $sourceDocument->payment_condition,
                'payment_mode' => $sourceDocument->payment_mode,
                'late_fee_interest' => $sourceDocument->late_fee_interest,
            ]);

            foreach ($sourceDocument->items as $item) {
                $newDocument->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'product_type' => $item->product_type,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'total_ht' => $item->total_ht,
                    'total_ttc' => $item->total_ttc,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                ]);
            }

            $po->transitionTo('FINALIZED');

            DB::commit();

            return $newDocument->load('customer', 'items', 'documentable', 'parent');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function createDeliveryNoteFromQuote(int $quoteId): Document
    {
        $sourceDocument = Document::with(['documentable', 'items', 'customer'])
            ->where('documentable_type', Quote::class)
            ->whereHas('documentable', function ($q) {
                $q->whereIn('status', ['FINALIZED', 'SENT', 'SIGNED']);
            })
            ->findOrFail($quoteId);

        $companyId = $this->getCompanyId();

        $calculated = [
            'total_ht' => $sourceDocument->total_ht,
            'total_tva' => $sourceDocument->total_tva,
            'total_ttc' => $sourceDocument->total_ttc,
        ];

        DB::beginTransaction();

        try {
            $document = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $sourceDocument->customer_id,
                'bank_account_id' => $sourceDocument->bank_account_id,
                'parent_document_id' => $sourceDocument->id,
                'number' => null,
                'total_ht' => $calculated['total_ht'],
                'total_tva' => $calculated['total_tva'],
                'total_ttc' => $calculated['total_ttc'],
                'global_discount_type' => $sourceDocument->global_discount_type,
                'global_discount_value' => $sourceDocument->global_discount_value,
                'global_discount_amount' => $sourceDocument->global_discount_amount,
                'notes' => $sourceDocument->notes,
                'terms' => $sourceDocument->terms,
                'intro_text' => $sourceDocument->intro_text,
                'footer_text' => $sourceDocument->footer_text,
                'conclusion_text' => $sourceDocument->conclusion_text,
                'documentable_type' => DeliveryNote::class,
                'documentable_id' => 0,
                'payment_condition' => $sourceDocument->payment_condition,
                'payment_mode' => $sourceDocument->payment_mode,
                'late_fee_interest' => $sourceDocument->late_fee_interest,
            ]);

            $deliveryNote = DeliveryNote::create([
                'status' => 'DRAFT',
                'delivery_date' => null,
            ]);

            $document->documentable_id = $deliveryNote->id;
            $document->save();

            foreach ($sourceDocument->items as $item) {
                $document->items()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'product_type' => $item->product_type,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'total_ht' => $item->total_ht,
                    'total_ttc' => $item->total_ttc,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                ]);
            }

            DB::commit();

            return $document->load('customer', 'items', 'documentable', 'parent');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    public function getAvailableActions(int $quoteId): array
    {
        $document = Document::with(['documentable', 'children.documentable'])
            ->where('documentable_type', Quote::class)
            ->findOrFail($quoteId);

        $quote = $document->documentable;
        $status = $quote->status;

        $actions = [
            'can_edit' => false,
            'can_finalize' => false,
            'can_send' => false,
            'can_sign' => false,
            'can_convert_to_invoice' => false,
            'can_convert_to_purchase_order' => false,
            'can_create_deposit' => false,
            'can_create_delivery_note' => false,
            'can_delete' => false,
            'can_download' => false,
            'can_duplicate' => false,
        ];

        if ($status === 'DRAFT') {
            $actions['can_edit'] = true;
            $actions['can_finalize'] = true;
            $actions['can_duplicate'] = true;
            $actions['can_delete'] = $document->children()->count() === 0;
        }

        if ($status === 'FINALIZED') {
            $actions['can_send'] = true;
            $actions['can_sign'] = true;
            $actions['can_download'] = true;
            $actions['can_duplicate'] = true;
            $actions['can_create_delivery_note'] = true;
        }

        if ($status === 'SENT') {
            $actions['can_sign'] = true;
            $actions['can_download'] = true;
            $actions['can_duplicate'] = true;
            $actions['can_create_delivery_note'] = true;
        }

        if ($status === 'SIGNED') {
            $actions['can_download'] = true;

            $hasPo = $document->hasChildrenOfType(PurchaseOrder::class);
            $actions['can_convert_to_purchase_order'] = !$hasPo;

            $hasStandardOrSolde = $document->children()
                ->where('documentable_type', Invoice::class)
                ->whereHasMorph('documentable', [Invoice::class], function ($q) {
                    $q->whereIn('type', ['STANDARD', 'SOLDE']);
                })
                ->exists();

            $actions['can_convert_to_invoice'] = !$hasStandardOrSolde;
            $actions['can_create_deposit'] = true;
            $actions['can_create_delivery_note'] = true;
        }

        if ($status === 'EXPIRED') {
            $actions['can_download'] = true;
            $actions['can_duplicate'] = true;
        }

        return $actions;
    }

    public function updateMetadata(int $id, array $data): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Quote::class) {
            throw new \InvalidArgumentException('Le document n\'est pas un devis.');
        }

        DB::beginTransaction();

        try {
            $document->update([
                'notes' => $data['notes'] ?? $document->notes,
                'terms' => $data['terms'] ?? $document->terms,
                'intro_text' => $data['intro_text'] ?? $document->intro_text,
                'footer_text' => $data['footer_text'] ?? $document->footer_text,
                'conclusion_text' => $data['conclusion_text'] ?? $document->conclusion_text,
                'payment_condition' => $data['payment_condition'] ?? $document->payment_condition,
                'payment_mode' => $data['payment_mode'] ?? $document->payment_mode,
                'late_fee_interest' => $data['late_fee_interest'] ?? $document->late_fee_interest,
            ]);

            DB::commit();

            return $document->fresh(['customer', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
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