<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    protected $fillable = [
        'user_id',
        'fournisseur_id',
        'invoice_number',
        'date',
        'total_ht',
        'tva_rate',
        'total_ttc',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'total_ht' => 'float',
        'tva_rate' => 'float',
        'total_ttc' => 'float',
    ];

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }
}