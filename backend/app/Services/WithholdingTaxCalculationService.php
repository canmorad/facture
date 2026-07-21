<?php

namespace App\Services;

class WithholdingTaxCalculationService
{
    private const RATES = [
        'professional_services' => 30.00,
        'construction_industry' => 20.00,
        'agriculture_fishing' => 15.00,
        'trade_commercial' => 15.00,
        'default' => 20.00,
    ];

    private const EXEMPTION_THRESHOLD_INDIVIDUAL = 5000.00;
    private const EXEMPTION_THRESHOLD_ENTERPRISE = 10000.00;

    public function calculate(array $data): array
    {
        $applyWithholding = $data['apply_withholding_tax'] ?? false;
        $amountHt = $data['amount_ht'] ?? 0;
        $amountTva = $data['amount_tva'] ?? 0;
        $supplierType = $data['supplier_type'] ?? 'enterprise';
        $supplierHasIce = $data['supplier_has_ice'] ?? false;
        $activitySector = $data['activity_sector'] ?? 'default';

        $shouldApply = $this->shouldApplyWithholdingTax(
            $applyWithholding,
            $amountHt,
            $supplierType,
            $supplierHasIce
        );

        if (!$shouldApply) {
            return [
                'apply_withholding_tax' => false,
                'withholding_tax_rate' => 0,
                'withholding_tax_amount' => 0,
                'amount_after_withholding' => $amountHt + $amountTva,
                'taxes_breakdown' => [],
            ];
        }

        $rate = $this->getRate($activitySector, $supplierType);

        $withholdingAmount = ($amountHt * $rate) / 100;
        $withholdingAmount = round($withholdingAmount, 2);

        $amountTtc = $amountHt + $amountTva;
        $amountAfterWithholding = $amountTtc - $withholdingAmount;

        $taxesBreakdown = [
            'withholding_tax' => [
                'rate' => $rate,
                'base_amount' => $amountHt,
                'amount' => $withholdingAmount,
                'description' => $this->getDescription($activitySector),
            ],
            'tva' => [
                'amount' => $amountTva,
                'description' => 'TVA',
            ],
            'calculated_at' => now()->toIso8601String(),
        ];

        return [
            'apply_withholding_tax' => true,
            'withholding_tax_rate' => $rate,
            'withholding_tax_amount' => $withholdingAmount,
            'amount_after_withholding' => $amountAfterWithholding,
            'taxes_breakdown' => $taxesBreakdown,
            'taxes' => $taxesBreakdown,
        ];
    }

    private function shouldApplyWithholdingTax(
        bool $applyRequested,
        float $amountHt,
        string $supplierType,
        bool $supplierHasIce
    ): bool {
        if (!$applyRequested) {
            return false;
        }

        if ($supplierType === 'enterprise' && $supplierHasIce) {
            return false;
        }

        $threshold = $supplierType === 'individual'
            ? self::EXEMPTION_THRESHOLD_INDIVIDUAL
            : self::EXEMPTION_THRESHOLD_ENTERPRISE;

        if ($amountHt < $threshold) {
            return false;
        }

        return true;
    }

    private function getRate(string $activitySector, string $supplierType): float
    {
        if ($supplierType === 'individual' && $activitySector !== 'default') {
            return self::RATES[$activitySector] ?? self::RATES['default'];
        }

        return self::RATES[$activitySector] ?? self::RATES['default'];
    }

    private function getDescription(string $activitySector): string
    {
        $descriptions = [
            'professional_services' => 'Retenue à la source - Services professionnels (30%)',
            'construction_industry' => 'Retenue à la source - BTP/Industrie (20%)',
            'agriculture_fishing' => 'Retenue à la source - Agriculture/Pêche (15%)',
            'trade_commercial' => 'Retenue à la source - Commerce (15%)',
            'default' => 'Retenue à la source (20%)',
        ];

        return $descriptions[$activitySector] ?? $descriptions['default'];
    }

    public function recalculate(\App\Models\PurchaseInvoice $invoice): array
    {
        return $this->calculate([
            'apply_withholding_tax' => $invoice->apply_withholding_tax,
            'amount_ht' => $invoice->amount_ht,
            'amount_tva' => $invoice->amount_tva,
            'supplier_type' => $invoice->fournisseur->supplier_type ?? 'enterprise',
            'supplier_has_ice' => !empty($invoice->fournisseur->ice ?? ''),
            'activity_sector' => 'default',
        ]);
    }
}
