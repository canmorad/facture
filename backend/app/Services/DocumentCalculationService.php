<?php

namespace App\Services;

class DocumentCalculationService
{
    public function calculate(array $items, ?string $discountType = null, float $discountValue = 0): array
    {
        $processedItems = [];
        $totalHt = 0;
        $totalTva = 0;

        foreach ($items as $item) {
            $quantity = $item['quantity'] ?? 0;
            $unitPrice = $item['unit_price'] ?? 0;
            $taxRate = $item['tax_rate'] ?? 20;

            $lineHt = $quantity * $unitPrice;
            $lineTva = $lineHt * ($taxRate / 100);
            $lineTtc = $lineHt + $lineTva;

            $itemDiscountAmount = 0;
            if (isset($item['discount_type']) && isset($item['discount_value'])) {
                if ($item['discount_type'] === 'percentage') {
                    $itemDiscountAmount = $lineHt * ($item['discount_value'] / 100);
                } else {
                    $itemDiscountAmount = $item['discount_value'];
                }
            }

            $processedItems[] = [
                'line_ht' => $lineHt,
                'line_tva' => $lineTva,
                'line_ttc' => $lineTtc,
                'tax_rate' => $taxRate,
                'discount_amount' => $itemDiscountAmount,
            ];

            $totalHt += $lineHt;
            $totalTva += $lineTva;
        }

        $totalTtc = $totalHt + $totalTva;

        $globalDiscountAmount = 0;
        if ($discountType && $discountValue > 0) {
            if ($discountType === 'percentage') {
                $globalDiscountAmount = $totalHt * ($discountValue / 100);
            } else {
                $globalDiscountAmount = $discountValue;
            }
        }

        $totalHtAfterDiscount = $totalHt - $globalDiscountAmount;

        return [
            'total_ht' => $totalHt,
            'total_tva' => $totalTva,
            'total_ttc' => $totalTtc,
            'total_ht_after_discount' => max(0, $totalHtAfterDiscount),
            'global_discount_amount' => $globalDiscountAmount,
            'processed_items' => $processedItems,
        ];
    }
}
