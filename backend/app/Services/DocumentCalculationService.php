<?php

namespace App\Services;

class DocumentCalculationService
{
    public function calculate(array $items, ?string $globalDiscountType, ?float $globalDiscountValue): array
    {
        $totalHt = 0.0;
        $totalWeightedTva = 0.0;
        $processedItems = [];

        foreach ($items as $item) {
            $subtotal = $item['quantity'] * $item['unit_price'];
            $discountAmount = 0.0;

            if (!empty($item['discount_type']) && $item['discount_value'] > 0) {
                if ($item['discount_type'] === 'percentage') {
                    $discountAmount = $subtotal * ($item['discount_value'] / 100);
                } else {
                    $discountAmount = (float) $item['discount_value'];
                }
            }

            $lineHt = $subtotal - $discountAmount;
            $totalHt += $lineHt;
            $totalWeightedTva += $lineHt * ($item['tax_rate'] / 100);

            $processedItems[] = [
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'line_ht' => $lineHt,
                'tax_rate' => $item['tax_rate'],
            ];
        }

        $globalDiscountAmount = 0.0;
        if ($globalDiscountType && $globalDiscountValue > 0) {
            if ($globalDiscountType === 'percentage') {
                $globalDiscountAmount = $totalHt * ($globalDiscountValue / 100);
            } else {
                $globalDiscountAmount = (float) $globalDiscountValue;
            }
        }

        $totalHtAfterDiscount = $totalHt - $globalDiscountAmount;
        $totalTva = $totalWeightedTva;

        if ($globalDiscountAmount > 0 && $totalHt > 0) {
            $totalTva = $totalWeightedTva * ($totalHtAfterDiscount / $totalHt);
        }

        $totalTtc = $totalHtAfterDiscount + $totalTva;

        return [
            'total_ht' => $totalHt,
            'total_tva' => $totalTva,
            'total_ttc' => $totalTtc,
            'total_ht_after_discount' => $totalHtAfterDiscount,
            'global_discount_amount' => $globalDiscountAmount,
            'processed_items' => $processedItems,
        ];
    }
}