<?php

namespace App\Services;

use App\Exceptions\ImmutableDocumentException;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceDeduction;
use App\Models\Deposit;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class InvoiceService
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
            ->where('documentable_type', Invoice::class)
            ->with(['customer.customerable', 'items', 'documentable', 'parent']);

        if ($status && in_array($status, ['DRAFT', 'FINALIZED', 'SENT', 'PAID', 'OVERDUE', 'CANCELLED'])) {
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

        $settings = $company->documentSettings()->where('document_type', 'INVOICE')->first();

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

        $invoiceType = $validated['type'] ?? 'STANDARD';

        if ($invoiceType === 'DEPOSIT' || $invoiceType === 'ACOMPTE') {
            return $this->createDepositDraft($validated, $companyId);
        }

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
                'documentable_type' => Invoice::class,
                'documentable_id' => 0,
                'payment_condition' => $validated['payment_condition'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? null,
                'late_fee_interest' => $validated['late_fee_interest'] ?? null,
            ]);

            $invoice = Invoice::create([
                'status' => 'DRAFT',
                'due_date' => $validated['due_date'] ?? null,
                'type' => $validated['type'] ?? 'STANDARD',
            ]);

            $document->documentable_id = $invoice->id;
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

        if ($document->documentable_type !== Invoice::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture.');
        }

        $companyId = $this->getCompanyId();
        $number = $this->numberingService->generateNumber('invoice', $companyId);

        DB::beginTransaction();

        try {
            $this->documentService->updateNumber($document, $number);

            $invoice = $document->documentable;
            $invoice->transitionTo('FINALIZED');

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

        if ($document->documentable_type !== Invoice::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture.');
        }

        DB::beginTransaction();

        try {
            $invoice = $document->documentable;
            $invoice->transitionTo('SENT');

            DB::commit();

            return $document->fresh(['customer.customerable', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function markPaid(int $id, ?array $deductionDepositIds = null): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Invoice::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture.');
        }

        DB::beginTransaction();

        try {
            $invoice = $document->documentable;

            if ($deductionDepositIds && !empty($deductionDepositIds)) {
                foreach ($deductionDepositIds as $depositId) {
                    $deposit = Deposit::findOrFail($depositId);

                    if ($deposit->status !== 'PAID') {
                        throw new \RuntimeException('L\'acompte #' . $depositId . ' n\'est pas encore payé.');
                    }

                    if ($deposit->company_id !== $this->getCompanyId()) {
                        throw new \RuntimeException('Acompte non autorisé.');
                    }

                    $alreadyDeducted = InvoiceDeduction::where('deducted_deposit_id', $depositId)->exists();
                    if ($alreadyDeducted) {
                        throw new \RuntimeException('L\'acompte #' . $depositId . ' a déjà été déduit.');
                    }

                    $depositAmount = $deposit->input_value;

                    $totalDeducted = InvoiceDeduction::where('invoice_id', $invoice->id)->sum('amount');
                    if (($totalDeducted + $depositAmount) > $document->total_ttc) {
                        throw new \RuntimeException('Le montant total des déductions ne peut pas dépasser le total de la facture.');
                    }

                    InvoiceDeduction::create([
                        'invoice_id' => $invoice->id,
                        'deducted_deposit_id' => $depositId,
                        'amount' => $depositAmount,
                    ]);
                }
            }

            $invoice->transitionTo('PAID');

            DB::commit();

            return $document->fresh(['customer.customerable', 'items', 'documentable', 'parent', 'documentable.deductions']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function markOverdue(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Invoice::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture.');
        }

        DB::beginTransaction();

        try {
            $invoice = $document->documentable;
            $invoice->transitionTo('OVERDUE');

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

        if ($document->documentable_type !== Invoice::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture.');
        }

        $hasCreditNotes = Document::where('parent_document_id', $document->id)
            ->where('documentable_type', \App\Models\CreditNote::class)
            ->exists();

        DB::beginTransaction();

        try {
            $invoice = $document->documentable;
            $invoice->transitionTo('CANCELLED');

            DB::commit();

            return $document->fresh(['customer.customerable', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function addDeduction(int $invoiceId, int $depositId): InvoiceDeduction
    {
        $document = Document::with('documentable')
            ->where('company_id', $this->getCompanyId())
            ->where('documentable_type', Invoice::class)
            ->findOrFail($invoiceId);

        $invoice = $document->documentable;

        $deposit = Deposit::findOrFail($depositId);

        if ($deposit->status !== 'PAID') {
            throw new \RuntimeException('L\'acompte n\'est pas encore payé.');
        }

        if ($deposit->company_id !== $this->getCompanyId()) {
            throw new \RuntimeException('Acompte non autorisé.');
        }

        $alreadyDeducted = InvoiceDeduction::where('deducted_deposit_id', $depositId)->exists();
        if ($alreadyDeducted) {
            throw new \RuntimeException('Cet acompte a déjà été déduit.');
        }

        $totalDeducted = InvoiceDeduction::where('invoice_id', $invoice->id)->sum('amount');
        $depositAmount = $deposit->input_value;

        if (($totalDeducted + $depositAmount) > $document->total_ttc) {
            throw new \RuntimeException('Le montant total des déductions ne peut pas dépasser le total de la facture.');
        }

        return InvoiceDeduction::create([
            'invoice_id' => $invoice->id,
            'deducted_deposit_id' => $depositId,
            'amount' => $depositAmount,
        ]);
    }

    public function getAvailableDeductions(int $invoiceId): array
    {
        $document = Document::with('documentable')
            ->where('company_id', $this->getCompanyId())
            ->where('documentable_type', Invoice::class)
            ->findOrFail($invoiceId);

        if (!$document->parent_document_id) {
            return [];
        }

        $parentDocument = $document->parent;
        if (!$parentDocument || $parentDocument->documentable_type !== \App\Models\Quote::class) {
            return [];
        }

        $alreadyDeductedDepositIds = InvoiceDeduction::where('invoice_id', $document->documentable_id)
            ->pluck('deducted_deposit_id')
            ->toArray();

        $deposits = Deposit::where('quote_id', $parentDocument->documentable_id)
            ->where('status', 'PAID')
            ->whereNotIn('id', $alreadyDeductedDepositIds)
            ->get();

        return $deposits->toArray();
    }

    public function getAvailableActions(int $invoiceId): array
    {
        $document = Document::with('documentable', 'children')
            ->where('company_id', $this->getCompanyId())
            ->where('documentable_type', Invoice::class)
            ->findOrFail($invoiceId);

        $invoice = $document->documentable;
        $status = $invoice->status;

        $actions = [
            'can_finalize' => false,
            'can_send' => false,
            'can_mark_paid' => false,
            'can_cancel' => false,
            'can_create_credit_note' => false,
            'can_download' => false,
            'can_edit' => false,
            'can_delete' => false,
        ];

        if ($status === 'DRAFT') {
            $actions['can_edit'] = true;
            $actions['can_finalize'] = true;
            $actions['can_delete'] = $document->children()->count() === 0;
        }

        if ($status === 'FINALIZED') {
            $actions['can_send'] = true;
            $actions['can_mark_paid'] = true;
            $actions['can_download'] = true;
        }

        if ($status === 'SENT') {
            $actions['can_mark_paid'] = true;
            $actions['can_download'] = true;
        }

        if ($status === 'PAID') {
            $actions['can_download'] = true;
            $actions['can_create_credit_note'] = true;
        }

        if ($status === 'OVERDUE') {
            $actions['can_mark_paid'] = true;
            $actions['can_cancel'] = true;
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

        if ($document->documentable_type !== Invoice::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture.');
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

            return $document->fresh(['customer.customerable', 'items', 'documentable', 'parent']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateItems(int $id, array $items): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Invoice::class) {
            throw new \InvalidArgumentException('Le document n\'est pas une facture.');
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

    protected function createDepositDraft(array $validated, int $companyId): Document
    {
        DB::beginTransaction();

        try {
            $totalHt = $validated['deposit_input_value'] ?? 0;
            $taxRate = $validated['tax_rate'] ?? 0;
            $totalTva = $totalHt * ($taxRate / 100);
            $totalTtc = $totalHt + $totalTva;

            $document = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $validated['customer_id'],
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'parent_document_id' => $validated['parent_document_id'] ?? null,
                'number' => null,
                'total_ht' => $totalHt,
                'total_tva' => $totalTva,
                'total_ttc' => $totalTtc,
                'global_discount_type' => null,
                'global_discount_value' => 0,
                'global_discount_amount' => 0,
                'notes' => $validated['notes'] ?? null,
                'terms' => $validated['terms'] ?? null,
                'intro_text' => $validated['intro_text'] ?? null,
                'footer_text' => $validated['footer_text'] ?? null,
                'conclusion_text' => $validated['conclusion_text'] ?? null,
                'documentable_type' => Invoice::class,
                'documentable_id' => 0,
                'payment_condition' => $validated['payment_condition'] ?? null,
                'payment_mode' => $validated['payment_mode'] ?? null,
                'late_fee_interest' => $validated['late_fee_interest'] ?? null,
            ]);

            $invoice = Invoice::create([
                'status' => 'DRAFT',
                'due_date' => $validated['due_date'] ?? null,
                'type' => 'ACOMPTE',
            ]);

            $document->documentable_id = $invoice->id;
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
                    'calculated_ttc' => $totalTtc,
                ]
            ]);

            DB::commit();

            return $document->load('customer.customerable', 'items', 'documentable', 'parent');
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