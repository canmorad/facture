<?php

namespace App\Services;

use App\Models\DocumentItem;

class DocumentItemService
{
    public function createMany(int $documentId, array $items): void
    {
        foreach ($items as $item) {
            DocumentItem::create([
                'document_id' => $documentId,
                'product_id' => $item['product_id'] ?? null,
                'product_type' => $item['product_type'] ?? null,
                'description' => $item['designation'] ?? $item['description'] ?? 'Article',
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'],
                'discount_type' => $item['discount_type'] ?? null,
                'discount_value' => $item['discount_value'] ?? 0,
                'total_ht' => $item['calculated_ht'],
                'total_ttc' => $item['calculated_ttc'],
            ]);
        }
    }
}