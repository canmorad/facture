<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'status',
        'expected_date',
        'confirmed_at',
    ];

    protected $casts = [
        'expected_date' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }
}