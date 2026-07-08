<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceDeduction extends Model
{
    protected $fillable = [
        'invoice_id',
        'deducted_deposit_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class, 'deducted_deposit_id');
    }
}