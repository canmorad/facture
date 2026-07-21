<?php

namespace App\Services;

use App\Exceptions\ImmutableDocumentException;
use App\Models\Document;
use App\Models\Proforma;
use App\Models\Invoice;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class ProformaService
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
        $validStatuses = ['DRAFT', 'FINALIZED', 'SENT', 'EXPIRED', 'CONVERTED', 'CANCELLED'];

        $filters = array_merge([
            'status' => null,
            'search' => null,
            'date_from' => null,
            'date_to' => null,
            'customer_id' => null,
        ], $filters);

        $query = Document::where('company_id', $companyId)
            ->where('documentable_type', Proforma::class)
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
            ->where('documentable_type', Proforma::class)
            ->with(['customer.customerable', 'items.product', 'items.product.category', 'documentable', 'parent']);

        if ($status && in_array($status, ['DRAFT', 'FINALIZED', 'SENT', 'EXPIRED', 'CONVERTED', 'CANCELLED'])) {
            $query->whereHas('documentable', function ($q) use ($status) {
                $q->where('status', $status);
            });
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getCreationData(?int $parentDocumentId = null): array
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

        $settings = $company->documentSettings()->where('document_type', 'PROFORMA')->first();

        $defaults = [
            'intro_text' => $settings->intro_text ?? '',
            'footer_text' => $settings->footer_text ?? '',
            'terms' => $settings->terms ?? '',
            'conclusion_text' => $settings->conclusion_text ?? '',
        ];

        $parentDocument = null;
        if ($parentDocumentId) {
            $parentDocument = Document::where('company_id', $company->id)
                ->with(['items', 'customer'])
                ->find($parentDocumentId);
        }

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
            'parent_document' => $parentDocument,
        ];
    }

    public function createDraft(array $validated): Document
    {
        $companyId = $this->getCompanyId();

        $calculated = $this->calculationService->calculate(
            $validated['items'] ?? [],
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
                'documentable_type' => Proforma::class,
                'documentable_id' => 0,
                'payment_condition' => $validated['payment_condition'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? null,
                'late_fee_interest' => $validated['late_fee_interest'] ?? null,
            ]);

            $proforma = Proforma::create([
                'status' => 'DRAFT',
                'validity_date' => $validated['validity_date'] ?? null,
            ]);

            $document->documentable_id = $proforma->id;
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

            return $document->load('customer.customerable', 'items', 'documentable', 'parent');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function finalize(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Proforma::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture proforma.');
        }

        $companyId = $this->getCompanyId();
        $number = $this->numberingService->generateNumber('proforma', $companyId);

        DB::beginTransaction();

        try {
            $this->documentService->updateNumber($document, $number);

            $proforma = $document->documentable;
            $proforma->transitionTo('FINALIZED');
            $proforma->finalized_at = now();
            $proforma->save();

            DB::commit();

            return $document->fresh(['customer.customerable', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function send(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Proforma::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture proforma.');
        }

        DB::beginTransaction();

        try {
            $proforma = $document->documentable;
            $proforma->transitionTo('SENT');
            $proforma->sent_at = now();
            $proforma->save();

            DB::commit();

            return $document->fresh(['customer.customerable', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function markExpired(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Proforma::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture proforma.');
        }

        DB::beginTransaction();

        try {
            $proforma = $document->documentable;
            $proforma->transitionTo('EXPIRED');
            $proforma->expired_at = now();
            $proforma->save();

            DB::commit();

            return $document->fresh(['customer.customerable', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function cancel(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Proforma::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture proforma.');
        }

        DB::beginTransaction();

        try {
            $proforma = $document->documentable;
            $proforma->transitionTo('CANCELLED');
            $proforma->cancelled_at = now();
            $proforma->save();

            DB::commit();

            return $document->fresh(['customer.customerable', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function convertToInvoice(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Proforma::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture proforma.');
        }

        $proforma = $document->documentable;

        if (!$proforma->canBeConverted()) {
            throw new \InvalidArgumentException('Cette proforma ne peut pas être convertie en facture.');
        }

        $companyId = $this->getCompanyId();
        $invoiceService = app(InvoiceService::class);

        DB::beginTransaction();

        try {
            // Prepare items from proforma
            $items = [];
            foreach ($document->items as $item) {
                $items[] = [
                    'product_id' => $item->product_id,
                    'designation' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'tax_rate' => $item->tax_rate,
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                ];
            }

            // Create invoice from proforma data
            $invoiceData = [
                'customer_id' => $document->customer_id,
                'bank_account_id' => $document->bank_account_id,
                'parent_document_id' => $document->id,
                'items' => $items,
                'type' => 'STANDARD',
                'due_date' => $proforma->validity_date ?? now()->addDays(30),
                'global_discount_type' => $document->global_discount_type,
                'global_discount_value' => $document->global_discount_value,
                'notes' => $document->notes,
                'terms' => $document->terms,
                'intro_text' => $document->intro_text,
                'footer_text' => $document->footer_text,
                'conclusion_text' => $document->conclusion_text,
                'payment_condition' => $document->payment_condition,
                'payment_mode' => $document->payment_mode,
                'late_fee_interest' => $document->late_fee_interest,
            ];

            $newInvoice = $invoiceService->createDraft($invoiceData);

            // Update proforma status
            $proforma->transitionTo('CONVERTED');
            $proforma->converted_at = now();
            $proforma->converted_to_invoice_id = $newInvoice->documentable->id;
            $proforma->save();

            DB::commit();

            return $newInvoice->load('customer.customerable', 'items', 'documentable', 'parent');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getAvailableActions(int $proformaId): array
    {
        $proforma = Proforma::with('document')->findOrFail($proformaId);
        $document = $proforma->document;

        if (!$document || $document->company_id !== $this->getCompanyId()) {
            throw new \RuntimeException('Facture proforma non autorisée.');
        }

        $status = $proforma->status;
        $isLocked = $document->is_locked;

        $actions = [
            'can_finalize' => false,
            'can_send' => false,
            'can_mark_expired' => false,
            'can_cancel' => false,
            'can_convert' => false,
            'can_download' => false,
            'can_edit' => false,
            'can_delete' => false,
        ];

        if ($status === 'DRAFT' && !$isLocked) {
            $actions['can_edit'] = true;
            $actions['can_finalize'] = true;
            $actions['can_delete'] = true;
        }

        if ($status === 'FINALIZED') {
            $actions['can_send'] = true;
            $actions['can_convert'] = true;
            $actions['can_download'] = true;
        }

        if ($status === 'SENT') {
            $actions['can_convert'] = true;
            $actions['can_mark_expired'] = $proforma->isExpired();
            $actions['can_download'] = true;
        }

        if ($status === 'EXPIRED') {
            $actions['can_convert'] = true;
            $actions['can_cancel'] = true;
            $actions['can_download'] = true;
        }

        if ($status === 'CONVERTED') {
            $actions['can_download'] = true;
        }

        if ($status === 'CANCELLED') {
            $actions['can_download'] = true;
        }

        return $actions;
    }

    public function updateMetadata(int $id, array $data): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Proforma::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture proforma.');
        }

        $document->guardImmutable();

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

            if (isset($data['validity_date'])) {
                $proforma = $document->documentable;
                $proforma->validity_date = $data['validity_date'];
                $proforma->save();
            }

            DB::commit();

            return $document->fresh(['customer.customerable', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateItems(int $id, array $items): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Proforma::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture proforma.');
        }

        $document->guardImmutable();

        $calculated = $this->calculationService->calculate(
            $items,
            $document->global_discount_type,
            $document->global_discount_value
        );

        DB::beginTransaction();

        try {
            $document->items()->delete();

            $itemsWithTotals = [];
            foreach ($items as $idx => $item) {
                $processed = $calculated['processed_items'][$idx];
                $lineHt = $processed['line_ht'];
                $lineTtc = $lineHt * (1 + $item['tax_rate'] / 100);
                $itemsWithTotals[] = array_merge($item, [
                    'calculated_ht' => $lineHt,
                    'calculated_ttc' => $lineTtc,
                ]);
            }

            $this->documentItemService->createMany($document->id, $itemsWithTotals);

            $document->update([
                'total_ht' => $calculated['total_ht'],
                'total_tva' => $calculated['total_tva'],
                'total_ttc' => $calculated['total_ttc'],
                'global_discount_amount' => $calculated['global_discount_amount'],
            ]);

            DB::commit();

            return $document->fresh(['customer.customerable', 'items', 'documentable', 'parent']);
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
