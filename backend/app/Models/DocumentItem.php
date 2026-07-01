<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentItem extends Model
{
    protected $fillable = [
        'document_id',
        'product_id',
        'product_type',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'discount_type',
        'discount_value',
        'total_ht',
        'total_ttc',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'tax_rate' => 'float',
        'discount_value' => 'float',
        'total_ht' => 'float',
        'total_ttc' => 'float',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}