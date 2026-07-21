<?php

namespace App\Services;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class PurchaseInvoiceService
{
    public function __construct(
        protected WithholdingTaxCalculationService $withholdingTaxService,
        protected DocumentCalculationService $documentCalculationService,
        protected GeminiAIService $aiService,
    ) {}

    /**
     * Analyse un fichier avec Gemini AI et retourne les données extraites
     */
    public function analyzeFile(UploadedFile $file): array
    {
        return $this->aiService->analyzeInvoice($file);
    }

    public function getCreationData(): array
    {
        $companyId = $this->getCompanyId();

        $suppliers = \App\Models\Fournisseur::where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        Log::info('PurchaseInvoiceService getCreationData', [
            'company_id' => $companyId,
            'suppliers_count' => $suppliers->count(),
            'suppliers' => $suppliers->map(fn($s) => ['id' => $s->id, 'name' => $s->name])->toArray(),
        ]);

        $taxRates = \App\Models\TaxRate::where('company_id', $companyId)
            ->where('is_actif', true)
            ->get();

        $products = \App\Models\Product::where('company_id', $companyId)
            ->orderBy('name')
            ->get();

        return [
            'suppliers' => $suppliers,
            'tax_rates' => $taxRates,
            'products' => $products,
            'default_tax_rate' => $taxRates->firstWhere('is_default', true)?->rate ?? 20,
        ];
    }

    public function create(array $data, ?UploadedFile $file = null): PurchaseInvoice
    {
        $companyId = $this->getCompanyId();

        DB::beginTransaction();

        try {
            $aiData = null;
            if ($file) {
                $aiResult = $this->aiService->analyzeInvoice($file);
                if ($aiResult['success'] && !empty($aiResult['data'])) {
                    $aiData = $aiResult['data'];
                    $data['is_ocr_extracted'] = true;
                    $data['ocr_raw_data'] = json_encode($aiResult);
                }
            }

            $calculation = $this->documentCalculationService->calculate(
                $data['items'] ?? [],
                $data['global_discount_type'] ?? null,
                $data['global_discount_value'] ?? 0,
            );

            $supplier = \App\Models\Fournisseur::where('company_id', $companyId)
                ->where('id', $data['fournisseur_id'])
                ->firstOrFail();

            $withholdingCalc = $this->withholdingTaxService->calculate([
                'apply_withholding_tax' => $data['apply_withholding_tax'] ?? $supplier->isSubjectToWithholdingTax(),
                'amount_ht' => $calculation['total_ht_after_discount'],
                'amount_tva' => $calculation['total_tva'],
                'supplier_type' => $supplier->supplier_type ?? 'enterprise',
                'supplier_has_ice' => !empty($supplier->ice),
                'activity_sector' => $data['activity_sector'] ?? 'default',
            ]);

            // RÈGLE COMPTABLE STRICTE : Pas de numéro auto-généré pour les brouillons
            // Le numéro interne sera généré uniquement lors de la validation (draft -> validated)
            $invoiceNumber = null;

            $invoice = PurchaseInvoice::create([
                'company_id' => $companyId,
                'fournisseur_id' => $data['fournisseur_id'],
                'user_id' => auth()->id(),
                'invoice_number' => $invoiceNumber,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? ($aiData['supplier_invoice_number'] ?? 'N/A'),
                'invoice_date' => $data['invoice_date'] ?? now()->format('Y-m-d'),
                'due_date' => $data['due_date'] ?? null,
                'amount_ht' => $calculation['total_ht'],
                'amount_tva' => $calculation['total_tva'],
                'amount_ttc' => $calculation['total_ttc'],
                'apply_withholding_tax' => $withholdingCalc['apply_withholding_tax'],
                'withholding_tax_rate' => $withholdingCalc['withholding_tax_rate'],
                'withholding_tax_amount' => $withholdingCalc['withholding_tax_amount'],
                'amount_after_withholding' => $withholdingCalc['amount_after_withholding'],
                'taxes' => $withholdingCalc['taxes'] ?? null,
                'global_discount_type' => $data['global_discount_type'] ?? null,
                'global_discount_value' => $data['global_discount_value'] ?? 0,
                'global_discount_amount' => $calculation['global_discount_amount'],
                'status' => 'draft',
                'is_ocr_extracted' => $data['is_ocr_extracted'] ?? false,
                'ocr_raw_data' => $data['ocr_raw_data'] ?? null,
                'notes' => $data['notes'] ?? null,
                'payment_terms' => $data['payment_terms'] ?? null,
                'payment_mode' => $data['payment_mode'] ?? null,
            ]);

            foreach ($calculation['processed_items'] as $index => $itemData) {
                $originalItem = $data['items'][$index];

                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'product_id' => $originalItem['product_id'] ?? null,
                    'designation' => $originalItem['designation'] ?? '',
                    'product_type' => $originalItem['product_type'] ?? null,
                    'quantity' => $originalItem['quantity'],
                    'unit_price' => $originalItem['unit_price'],
                    'total_ht' => $itemData['line_ht'],
                    'tax_rate' => $itemData['tax_rate'],
                    'total_tva' => $itemData['line_ht'] * ($itemData['tax_rate'] / 100),
                    'total_ttc' => $itemData['line_ht'] + ($itemData['line_ht'] * ($itemData['tax_rate'] / 100)),
                    'discount_type' => $originalItem['discount_type'] ?? null,
                    'discount_value' => $originalItem['discount_value'] ?? 0,
                    'discount_amount' => $itemData['discount_amount'],
                ]);
            }

            DB::commit();

            return $invoice->load(['fournisseur', 'items']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            throw new \InvalidArgumentException('Le fournisseur sélectionné est invalide ou n\'existe pas.');
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            DB::rollBack();
            // Check if it's a duplicate supplier_invoice_number
            if (str_contains($e->getMessage(), 'uq_purchase_inv_company_supplier')) {
                throw new \InvalidArgumentException('Une facture avec le numéro "' . ($data['supplier_invoice_number'] ?? 'N/A') . '" existe déjà pour ce fournisseur.');
            }
            Log::error('PurchaseInvoiceService create error: ' . $e->getMessage(), [
                'exception' => $e,
                'data' => $data,
            ]);
            throw new \RuntimeException('Une erreur est survenue lors de la création de la facture.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PurchaseInvoiceService create error: ' . $e->getMessage(), [
                'exception' => $e,
                'data' => $data,
            ]);
            throw new \RuntimeException('Une erreur est survenue lors de la création de la facture.');
        }
    }

    public function update(PurchaseInvoice $invoice, array $data, ?UploadedFile $file = null): PurchaseInvoice
    {
        $companyId = $this->getCompanyId();

        if ($invoice->company_id !== $companyId) {
            throw new \RuntimeException('Unauthorized access to purchase invoice.');
        }

        DB::beginTransaction();

        try {
            if ($file) {
                $aiResult = $this->aiService->analyzeInvoice($file);
                if ($aiResult['success']) {
                    $data['is_ocr_extracted'] = true;
                    $data['ocr_raw_data'] = json_encode($aiResult);
                }
            }

            if (isset($data['items'])) {
                $calculation = $this->documentCalculationService->calculate(
                    $data['items'],
                    $data['global_discount_type'] ?? $invoice->global_discount_type,
                    $data['global_discount_value'] ?? $invoice->global_discount_value,
                );

                $supplier = $invoice->fournisseur;

                $withholdingCalc = $this->withholdingTaxService->calculate([
                    'apply_withholding_tax' => $data['apply_withholding_tax'] ?? $invoice->apply_withholding_tax,
                    'amount_ht' => $calculation['total_ht_after_discount'],
                    'amount_tva' => $calculation['total_tva'],
                    'supplier_type' => $supplier->supplier_type ?? 'enterprise',
                    'supplier_has_ice' => !empty($supplier->ice),
                    'activity_sector' => $data['activity_sector'] ?? 'default',
                ]);

                $invoice->update([
                    'amount_ht' => $calculation['total_ht'],
                    'amount_tva' => $calculation['total_tva'],
                    'amount_ttc' => $calculation['total_ttc'],
                    'apply_withholding_tax' => $withholdingCalc['apply_withholding_tax'],
                    'withholding_tax_rate' => $withholdingCalc['withholding_tax_rate'],
                    'withholding_tax_amount' => $withholdingCalc['withholding_tax_amount'],
                    'amount_after_withholding' => $withholdingCalc['amount_after_withholding'],
                    'taxes' => $withholdingCalc['taxes'] ?? null,
                    'global_discount_type' => $data['global_discount_type'] ?? $invoice->global_discount_type,
                    'global_discount_value' => $data['global_discount_value'] ?? $invoice->global_discount_value,
                    'global_discount_amount' => $calculation['global_discount_amount'],
                ]);

                $invoice->items()->delete();
                foreach ($calculation['processed_items'] as $index => $itemData) {
                    $originalItem = $data['items'][$index];

                    PurchaseInvoiceItem::create([
                        'purchase_invoice_id' => $invoice->id,
                        'product_id' => $originalItem['product_id'] ?? null,
                        'designation' => $originalItem['designation'] ?? '',
                        'product_type' => $originalItem['product_type'] ?? null,
                        'quantity' => $originalItem['quantity'],
                        'unit_price' => $originalItem['unit_price'],
                        'total_ht' => $itemData['line_ht'],
                        'tax_rate' => $itemData['tax_rate'],
                        'total_tva' => $itemData['line_ht'] * ($itemData['tax_rate'] / 100),
                        'total_ttc' => $itemData['line_ht'] + ($itemData['line_ht'] * ($itemData['tax_rate'] / 100)),
                        'discount_type' => $originalItem['discount_type'] ?? null,
                        'discount_value' => $originalItem['discount_value'] ?? 0,
                        'discount_amount' => $itemData['discount_amount'],
                    ]);
                }
            }

            $invoice->update([
                'fournisseur_id' => $data['fournisseur_id'] ?? $invoice->fournisseur_id,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? $invoice->supplier_invoice_number,
                'invoice_date' => $data['invoice_date'] ?? $invoice->invoice_date,
                'due_date' => $data['due_date'] ?? $invoice->due_date,
                'notes' => $data['notes'] ?? $invoice->notes,
                'payment_terms' => $data['payment_terms'] ?? $invoice->payment_terms,
                'payment_mode' => $data['payment_mode'] ?? $invoice->payment_mode,
            ]);

            DB::commit();

            return $invoice->load(['fournisseur', 'items']);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            DB::rollBack();
            if (str_contains($e->getMessage(), 'uq_purchase_inv_company_supplier')) {
                throw new \InvalidArgumentException('Une facture avec le numéro "' . ($data['supplier_invoice_number'] ?? 'N/A') . '" existe déjà.');
            }
            Log::error('PurchaseInvoiceService update error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw new \RuntimeException('Une erreur est survenue lors de la modification de la facture.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('PurchaseInvoiceService update error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    public function validate(PurchaseInvoice $invoice): PurchaseInvoice
    {
        $companyId = $this->getCompanyId();

        if ($invoice->company_id !== $companyId) {
            throw new \RuntimeException('Unauthorized access to purchase invoice.');
        }

        if ($invoice->status !== 'draft') {
            throw new \RuntimeException('Only draft invoices can be validated.');
        }

        // RÈGLE COMPTABLE STRICTE : Le numéro est généré UNIQUEMENT lors de la validation
        $invoiceNumber = $this->generateInvoiceNumber($companyId);

        $invoice->update([
            'invoice_number' => $invoiceNumber,
            'status' => 'validated',
            'validated_at' => now(),
            'validated_by' => auth()->id(),
        ]);

        return $invoice->load(['validator']);
    }

    public function markAsPaid(PurchaseInvoice $invoice): PurchaseInvoice
    {
        $companyId = $this->getCompanyId();

        if ($invoice->company_id !== $companyId) {
            throw new \RuntimeException('Unauthorized access to purchase invoice.');
        }

        // Only validated or overdue invoices can be marked as paid
        if (!in_array($invoice->status, ['validated', 'overdue'])) {
            throw new \RuntimeException('Only validated or overdue invoices can be marked as paid.');
        }

        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return $invoice->fresh(['fournisseur', 'items']);
    }

    public function markAsUnpaid(PurchaseInvoice $invoice): PurchaseInvoice
    {
        $companyId = $this->getCompanyId();

        if ($invoice->company_id !== $companyId) {
            throw new \RuntimeException('Unauthorized access to purchase invoice.');
        }

        // Only paid invoices can be marked as unpaid
        if ($invoice->status !== 'paid') {
            throw new \RuntimeException('Only paid invoices can be marked as unpaid.');
        }

        $invoice->update([
            'status' => 'validated',
            'paid_at' => null,
        ]);

        return $invoice->fresh(['fournisseur', 'items']);
    }

    public function cancel(PurchaseInvoice $invoice): PurchaseInvoice
    {
        $companyId = $this->getCompanyId();

        if ($invoice->company_id !== $companyId) {
            throw new \RuntimeException('Unauthorized access to purchase invoice.');
        }

        // Only validated or overdue invoices can be cancelled
        if (!in_array($invoice->status, ['validated', 'overdue'])) {
            throw new \RuntimeException('Only validated or overdue invoices can be cancelled.');
        }

        $invoice->update([
            'status' => 'cancelled',
        ]);

        return $invoice->fresh(['fournisseur', 'items']);
    }

    public function index(): array
    {
        $companyId = $this->getCompanyId();

        return PurchaseInvoice::forCompany($companyId)
            ->with(['fournisseur'])
            ->latest('invoice_date')
            ->get()
            ->toArray();
    }

    private function generateInvoiceNumber(int $companyId): string
    {
        $year = now()->format('Y');
        $prefix = config('app.purchase_invoice_prefix', 'FA');

        $lastInvoice = PurchaseInvoice::forCompany($companyId)
            ->whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) preg_replace('/[^0-9]/', '', $lastInvoice->invoice_number);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $newNumber);
    }

    protected function getCompanyId(): int
    {
        return config('app.current_company_id')
            ?? throw new \RuntimeException('Aucune entreprise sélectionnée.');
    }
}
