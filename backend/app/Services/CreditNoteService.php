<?php

namespace App\Services;

use App\Models\Document;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class CreditNoteService
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
            ->where('documentable_type', CreditNote::class)
            ->with(['customer', 'items', 'documentable', 'parent']);

        if ($status && in_array($status, ['DRAFT', 'FINALIZED', 'SENT', 'APPLIED'])) {
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

        $settings = $company->documentSettings()->where('document_type', 'CREDIT_NOTE')->first();

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

    public function createFromInvoice(int $invoiceId, array $data): Document
    {
        $sourceDocument = Document::with(['documentable', 'items', 'customer'])
            ->where('company_id', $this->getCompanyId())
            ->where('documentable_type', Invoice::class)
            ->findOrFail($invoiceId);

        $invoice = $sourceDocument->documentable;

        // Vérifier que le type d'avoir correspond au type de facture
        $creditNoteType = $data['type'] ?? 'STANDARD';

        if ($invoice->type === 'ACOMPTE' && $creditNoteType !== 'DEPOSIT') {
            throw new \RuntimeException('Un avoir d\'acompte ne peut s\'appliquer qu\'à une facture de type ACOMPTE.');
        }

        if ($invoice->type !== 'ACOMPTE' && $creditNoteType === 'DEPOSIT') {
            throw new \RuntimeException('Un avoir d\'acompte ne peut s\'appliquer qu\'à une facture de type ACOMPTE.');
        }

        $companyId = $this->getCompanyId();

        DB::beginTransaction();

        try {
            $number = $this->numberingService->generateNumber('credit_note', $companyId);

            $creditNote = CreditNote::create([
                'type' => $creditNoteType,
                'reason' => $data['reason'] ?? null,
                'status' => 'DRAFT',
            ]);

            $newDocument = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $sourceDocument->customer_id,
                'bank_account_id' => $sourceDocument->bank_account_id,
                'parent_document_id' => $sourceDocument->id,
                'number' => $number,
                'total_ht' => $data['total_ht'] ?? $sourceDocument->total_ht,
                'total_tva' => $data['total_tva'] ?? $sourceDocument->total_tva,
                'total_ttc' => $data['total_ttc'] ?? $sourceDocument->total_ttc,
                'global_discount_type' => $sourceDocument->global_discount_type,
                'global_discount_value' => $sourceDocument->global_discount_value,
                'global_discount_amount' => $sourceDocument->global_discount_amount,
                'notes' => $data['notes'] ?? 'Avoir pour facture ' . ($sourceDocument->number ?? '#'.$sourceDocument->id),
                'terms' => $sourceDocument->terms,
                'intro_text' => $sourceDocument->intro_text,
                'footer_text' => $sourceDocument->footer_text,
                'conclusion_text' => $sourceDocument->conclusion_text,
                'documentable_type' => CreditNote::class,
                'documentable_id' => $creditNote->id,
                'payment_condition' => $sourceDocument->payment_condition,
                'payment_mode' => $sourceDocument->payment_mode,
                'late_fee_interest' => $sourceDocument->late_fee_interest,
            ]);

            if (!empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $newDocument->items()->create([
                        'product_id' => $item['product_id'] ?? null,
                        'description' => $item['description'],
                        'product_type' => $item['product_type'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'tax_rate' => $item['tax_rate'],
                        'total_ht' => $item['total_ht'] ?? ($item['quantity'] * $item['unit_price']),
                        'total_ttc' => $item['total_ttc'] ?? ($item['quantity'] * $item['unit_price'] * (1 + $item['tax_rate'] / 100)),
                        'discount_type' => $item['discount_type'] ?? null,
                        'discount_value' => $item['discount_value'] ?? 0,
                    ]);
                }
            } else {
                // Copier les items de la facture source
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
            }

            DB::commit();

            return $newDocument->load('customer', 'items', 'documentable', 'parent');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function finalize(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== CreditNote::class) {
            throw new \InvalidArgumentException('Le document n\'est pas un avoir.');
        }

        $companyId = $this->getCompanyId();

        if (!$document->number) {
            $number = $this->numberingService->generateNumber('credit_note', $companyId);
            $this->documentService->updateNumber($document, $number);
        }

        DB::beginTransaction();

        try {
            $creditNote = $document->documentable;
            $creditNote->transitionTo('FINALIZED');

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

        if ($document->documentable_type !== CreditNote::class) {
            throw new \InvalidArgumentException('Le document n\'est pas un avoir.');
        }

        DB::beginTransaction();

        try {
            $creditNote = $document->documentable;
            $creditNote->transitionTo('SENT');

            DB::commit();

            return $document->fresh(['customer', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function apply(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== CreditNote::class) {
            throw new \InvalidArgumentException('Le document n\'est pas un avoir.');
        }

        DB::beginTransaction();

        try {
            $creditNote = $document->documentable;
            $creditNote->transitionTo('APPLIED');

            DB::commit();

            return $document->fresh(['customer', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function getAvailableActions(int $creditNoteId): array
    {
        $document = Document::with('documentable')
            ->where('company_id', $this->getCompanyId())
            ->where('documentable_type', CreditNote::class)
            ->findOrFail($creditNoteId);

        $creditNote = $document->documentable;
        $status = $creditNote->status;

        $actions = [
            'can_finalize' => false,
            'can_send' => false,
            'can_apply' => false,
            'can_download' => false,
            'can_delete' => false,
        ];

        if ($status === 'DRAFT') {
            $actions['can_finalize'] = true;
            $actions['can_delete'] = true;
        }

        if ($status === 'FINALIZED') {
            $actions['can_send'] = true;
            $actions['can_download'] = true;
        }

        if ($status === 'SENT') {
            $actions['can_apply'] = true;
            $actions['can_download'] = true;
        }

        if ($status === 'APPLIED') {
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
}