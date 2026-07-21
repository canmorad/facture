<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DeliveryNote;
use App\Models\Invoice;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class DeliveryNoteService
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
        $validStatuses = ['DRAFT', 'FINALIZED', 'SENT', 'DELIVERED'];

        $filters = array_merge([
            'status' => null,
            'search' => null,
            'date_from' => null,
            'date_to' => null,
            'customer_id' => null,
        ], $filters);

        $query = Document::where('company_id', $companyId)
            ->where('documentable_type', DeliveryNote::class)
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
            ->where('documentable_type', DeliveryNote::class)
            ->with(['customer', 'items', 'documentable', 'parent']);

        if ($status && in_array($status, ['DRAFT', 'FINALIZED', 'SENT', 'DELIVERED'])) {
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

        $settings = $company->documentSettings()->where('document_type', 'DELIVERY_NOTE')->first();

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

        return DB::transaction(function () use ($validated, $companyId, $calculated) {
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
                'documentable_type' => DeliveryNote::class,
                'documentable_id' => 0,
                'payment_condition' => $validated['payment_condition'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? null,
                'late_fee_interest' => $validated['late_fee_interest'] ?? null,
            ]);

            $deliveryNote = DeliveryNote::create([
                'status' => 'DRAFT',
                'delivery_date' => $validated['delivery_date'] ?? null,
            ]);

            $document->documentable_id = $deliveryNote->id;
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

            return $document->load('customer', 'items');
        });
    }

    public function finalize(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== DeliveryNote::class) {
            throw new \InvalidArgumentException('Le document n\'est pas un bon de livraison.');
        }

        $companyId = $this->getCompanyId();

        $number = $this->numberingService->generateNumber('delivery_note', $companyId);

        return DB::transaction(function () use ($document, $number) {
            $this->documentService->updateNumber($document, $number);

            $deliveryNote = $document->documentable;
            $deliveryNote->transitionTo('FINALIZED');

            return $document->fresh(['customer', 'items', 'documentable', 'parent']);
        });
    }

    public function send(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== DeliveryNote::class) {
            throw new \InvalidArgumentException('Le document n\'est pas un bon de livraison.');
        }

        DB::beginTransaction();

        try {
            $deliveryNote = $document->documentable;
            $deliveryNote->transitionTo('SENT');

            DB::commit();

            return $document->fresh(['customer', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function markDelivered(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== DeliveryNote::class) {
            throw new \InvalidArgumentException('Le document n\'est pas un bon de livraison.');
        }

        DB::beginTransaction();

        try {
            $deliveryNote = $document->documentable;
            $deliveryNote->transitionTo('DELIVERED');

            DB::commit();

            return $document->fresh(['customer', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function convertToInvoice(int $deliveryNoteId, ?string $invoiceType = 'STANDARD'): Document
    {
        $sourceDocument = Document::with(['documentable', 'items', 'customer', 'parent', 'parent.documentable'])
            ->where('documentable_type', DeliveryNote::class)
            ->findOrFail($deliveryNoteId);

        $companyId = $this->getCompanyId();

        DB::beginTransaction();

        try {
            $customerId = $sourceDocument->customer_id;
            $parentDocumentId = $sourceDocument->id;

            // Pour un brouillon, pas de numéro officiel généré automatiquement
            // Le numéro sera attribué lors de la finalisation
            $number = null;

            $invoice = Invoice::create([
                'status' => 'DRAFT',
                'due_date' => null,
                'type' => $invoiceType,
            ]);

            $newDocument = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'bank_account_id' => $sourceDocument->bank_account_id,
                'parent_document_id' => $parentDocumentId,
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

            $invoice->transitionTo('FINALIZED');

            DB::commit();

            return $newDocument->load('customer', 'items', 'documentable', 'parent');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getAvailableActions(int $deliveryNoteId): array
    {
        $deliveryNote = DeliveryNote::with('document')->findOrFail($deliveryNoteId);
        $document = $deliveryNote->document;

        if (!$document || $document->company_id !== $this->getCompanyId()) {
            throw new \RuntimeException('Bon de livraison non autorisé.');
        }

        $status = $deliveryNote->status;

        $hasInvoice = $document->children()
            ->where('documentable_type', Invoice::class)
            ->exists();

        $actions = [
            'can_finalize' => false,
            'can_send' => false,
            'can_mark_delivered' => false,
            'can_convert_to_invoice' => false,
            'can_download' => false,
            'can_delete' => false,
        ];

        if ($status === 'DRAFT') {
            $actions['can_finalize'] = true;
            $actions['can_delete'] = $document->children()->count() === 0;
        }

        if ($status === 'FINALIZED') {
            $actions['can_send'] = true;
            $actions['can_mark_delivered'] = true;
            $actions['can_convert_to_invoice'] = !$hasInvoice;
            $actions['can_download'] = true;
        }

        if ($status === 'SENT') {
            $actions['can_mark_delivered'] = true;
            $actions['can_convert_to_invoice'] = !$hasInvoice;
            $actions['can_download'] = true;
        }

        if ($status === 'DELIVERED') {
            $actions['can_convert_to_invoice'] = !$hasInvoice;
            $actions['can_download'] = true;
        }

        return $actions;
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

    public function consolidateToInvoice(array $deliveryNoteIds, string $invoiceType = 'STANDARD'): Document
    {
        if (count($deliveryNoteIds) < 2) {
            throw new \InvalidArgumentException('Au moins 2 bons de livraison sont requis pour la consolidation.');
        }

        $companyId = $this->getCompanyId();

        $documents = Document::where('company_id', $companyId)
            ->whereIn('id', $deliveryNoteIds)
            ->where('documentable_type', DeliveryNote::class)
            ->with(['documentable', 'customer', 'items'])
            ->get();

        if ($documents->count() !== count($deliveryNoteIds)) {
            throw new \InvalidArgumentException('Certains bons de livraison sont introuvables ou non autorisés.');
        }

        $customerId = $documents->first()->customer_id;
        if ($documents->pluck('customer_id')->unique()->count() > 1) {
            throw new \InvalidArgumentException('Tous les bons de livraison doivent appartenir au même client.');
        }

        foreach ($documents as $doc) {
            $status = $doc->documentable->getAttribute('status');
            if (!in_array($status, ['DELIVERED', 'FINALIZED'])) {
                throw new \InvalidArgumentException("Le bon de livraison #{$doc->id} n'est pas livré.");
            }
        }

        DB::beginTransaction();

        try {
            $totalHt = $documents->sum('total_ht');
            $totalTva = $documents->sum('total_tva');
            $totalTtc = $documents->sum('total_ttc');

            $invoice = Invoice::create([
                'status' => 'DRAFT',
                'due_date' => now()->addDays(30)->toDateString(),
                'type' => $invoiceType,
            ]);

            // Pour un brouillon, pas de numéro officiel généré automatiquement
            // Le numéro sera attribué lors de la finalisation
            $number = null;

            $consolidatedDocument = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $customerId,
                'bank_account_id' => $documents->first()->bank_account_id,
                'parent_document_id' => null,
                'number' => $number,
                'total_ht' => $totalHt,
                'total_tva' => $totalTva,
                'total_ttc' => $totalTtc,
                'global_discount_type' => null,
                'global_discount_value' => 0,
                'global_discount_amount' => 0,
                'notes' => 'Facture consolidée à partir de ' . count($documents) . ' bons de livraison.',
                'terms' => $documents->first()->terms,
                'intro_text' => $documents->first()->intro_text,
                'footer_text' => $documents->first()->footer_text,
                'conclusion_text' => $documents->first()->conclusion_text,
                'documentable_type' => Invoice::class,
                'documentable_id' => $invoice->id,
                'payment_condition' => $documents->first()->payment_condition,
                'payment_mode' => $documents->first()->payment_mode,
                'late_fee_interest' => $documents->first()->late_fee_interest,
            ]);

            $itemGroup = [];
            foreach ($documents as $doc) {
                foreach ($doc->items as $item) {
                    $key = md5($item->description . $item->unit_price . $item->tax_rate);
                    if (!isset($itemGroup[$key])) {
                        $itemGroup[$key] = [
                            'product_id' => $item->product_id,
                            'description' => $item->description,
                            'product_type' => $item->product_type,
                            'unit_price' => $item->unit_price,
                            'tax_rate' => $item->tax_rate,
                            'discount_type' => $item->discount_type,
                            'discount_value' => $item->discount_value,
                            'quantity' => 0,
                            'total_ht' => 0,
                            'total_ttc' => 0,
                        ];
                    }
                    $itemGroup[$key]['quantity'] += $item->quantity;
                    $itemGroup[$key]['total_ht'] += $item->total_ht;
                    $itemGroup[$key]['total_ttc'] += $item->total_ttc;
                }
            }

            foreach ($itemGroup as $item) {
                $consolidatedDocument->items()->create([
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'product_type' => $item['product_type'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'tax_rate' => $item['tax_rate'],
                    'total_ht' => $item['total_ht'],
                    'total_ttc' => $item['total_ttc'],
                    'discount_type' => $item['discount_type'],
                    'discount_value' => $item['discount_value'],
                ]);
            }

            foreach ($documents as $doc) {
                $consolidatedDocument->children()->create([
                    'company_id' => $companyId,
                    'customer_id' => $doc->customer_id,
                    'parent_document_id' => $doc->id,
                    'number' => null,
                    'total_ht' => $doc->total_ht,
                    'total_tva' => $doc->total_tva,
                    'total_ttc' => $doc->total_ttc,
                    'documentable_type' => Invoice::class,
                    'documentable_id' => $invoice->id,
                ]);
            }

            $invoice->transitionTo('FINALIZED');
            $this->fiscalService->lockDocument($consolidatedDocument, 'finalized_document_immutable');

            DB::commit();

            return $consolidatedDocument->load('customer', 'items', 'documentable', 'parent');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getConsolidatableDeliveryNotes(int $customerId): array
    {
        $companyId = $this->getCompanyId();

        return Document::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->where('documentable_type', DeliveryNote::class)
            ->whereHas('documentable', function ($q) {
                $q->whereIn('status', ['DELIVERED', 'FINALIZED']);
            })
            ->whereDoesntHave('children', function ($q) {
                $q->where('documentable_type', \App\Models\Invoice::class);
            })
            ->with(['documentable', 'items'])
            ->get()
            ->map(function ($doc) {
                return [
                    'id' => $doc->id,
                    'number' => $doc->number,
                    'total_ttc' => $doc->total_ttc,
                    'status' => $doc->documentable->status,
                    'delivery_date' => $doc->documentable->delivery_date,
                ];
            })
            ->toArray();
    }
}