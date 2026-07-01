<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Invoice;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class InvoiceService
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
            ->where('documentable_type', Invoice::class)
            ->with(['customer', 'items', 'documentable']);

        if ($status && in_array($status, ['DRAFT', 'SENT', 'FINALIZED', 'PAID', 'OVERDUE', 'CANCELLED'])) {
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

        if ($validated['type'] === 'STANDARD') {
            return $this->createStandardInvoice($validated, $companyId);
        }

        return $this->createDepositInvoice($validated, $companyId);
    }

    protected function createStandardInvoice(array $validated, int $companyId): Document
    {
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
                'due_date' => $validated['due_date'],
                'paid_at' => null,
                'type' => 'STANDARD',
                'deposit_input_type' => null,
                'deposit_input_value' => 0,
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

            return $document->load('customer', 'items');
        });
    }

    protected function createDepositInvoice(array $validated, int $companyId): Document
    {
        $depositInputType = $validated['deposit_input_type'];
        $depositInputValue = $validated['deposit_input_value'];
        $taxRate = $validated['tax_rate'];

        $totalHt = $depositInputValue;
        $totalTva = $totalHt * ($taxRate / 100);
        $totalTtc = $totalHt + $totalTva;

        return DB::transaction(function () use ($validated, $companyId, $totalHt, $totalTva, $totalTtc, $depositInputType, $depositInputValue, $taxRate) {
            $document = $this->documentService->create([
                'company_id' => $companyId,
                'customer_id' => $validated['customer_id'],
                'bank_account_id' => $validated['bank_account_id'] ?? null,
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
                'due_date' => $validated['due_date'],
                'paid_at' => null,
                'type' => 'DEPOSIT',
                'deposit_input_type' => $depositInputType,
                'deposit_input_value' => $depositInputValue,
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

            return $document->load('customer', 'items');
        });
    }

    public function finalize(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Invoice::class) {
            throw new \InvalidArgumentException('Document is not an Invoice.');
        }

        $companyId = $this->getCompanyId();

        $number = $this->numberingService->generateNumber('invoice', $companyId);

        return DB::transaction(function () use ($document, $number) {
            $this->documentService->updateNumber($document, $number);

            $invoice = $document->documentable;
            $invoice->status = 'FINALIZED';
            $invoice->save();

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