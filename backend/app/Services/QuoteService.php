<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Quote;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class QuoteService
{
    protected ?int $companyId = null;

    public function __construct(
        protected DocumentCalculationService $calculationService,
        protected NumberingService $numberingService,
        protected DocumentService $documentService,
        protected DocumentItemService $documentItemService
    ) {
    }

    public function setCompanyId(?int $companyId): self
    {
        $this->companyId = $companyId;
        return $this;
    }

    public function getAll(?string $status = null)
    {
        $companyId = $this->getCompanyId();

        $query = Document::where('company_id', $companyId)
            ->where('documentable_type', Quote::class)
            ->with(['customer', 'items', 'documentable']);

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

            return $document->load('customer', 'items');
        });
    }

    public function finalize(int $id): Document
    {
        $document = $this->documentService->findOrFail($id);

        if ($document->documentable_type !== Quote::class) {
            throw new \InvalidArgumentException('Document is not a Quote.');
        }

        $companyId = $this->getCompanyId();

        $number = $this->numberingService->generateNumber('quote', $companyId);

        return DB::transaction(function () use ($document, $number) {
            $this->documentService->updateNumber($document, $number);

            $quote = $document->documentable;
            $quote->status = 'FINALIZED';
            $quote->save();

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