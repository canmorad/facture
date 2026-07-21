<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseInvoice extends Model
{
    use SoftDeletes, LogsActivityTrait;

    protected $fillable = [
        'company_id',
        'fournisseur_id',
        'user_id',
        'invoice_number',
        'supplier_invoice_number',
        'invoice_date',
        'due_date',
        'amount_ht',
        'amount_tva',
        'amount_ttc',
        'apply_withholding_tax',
        'withholding_tax_rate',
        'withholding_tax_amount',
        'amount_after_withholding',
        'taxes',
        'global_discount_type',
        'global_discount_value',
        'global_discount_amount',
        'status',
        'paid_at',
        'is_ocr_extracted',
        'ocr_raw_data',
        'media_id',
        'notes',
        'payment_terms',
        'payment_mode',
        'validated_at',
        'validated_by',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'date',
        'validated_at' => 'datetime',
        'amount_ht' => 'decimal:2',
        'amount_tva' => 'decimal:2',
        'amount_ttc' => 'decimal:2',
        'withholding_tax_rate' => 'decimal:2',
        'withholding_tax_amount' => 'decimal:2',
        'amount_after_withholding' => 'decimal:2',
        'global_discount_value' => 'decimal:2',
        'global_discount_amount' => 'decimal:2',
        'apply_withholding_tax' => 'boolean',
        'is_ocr_extracted' => 'boolean',
        'taxes' => 'array',
    ];

    protected $attributes = [
        'apply_withholding_tax' => false,
        'withholding_tax_rate' => 0,
        'status' => 'draft',
    ];

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeValidated($query)
    {
        return $query->where('status', 'validated');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function getFormattedAmountHtAttribute(): string
    {
        return number_format($this->amount_ht, 2, ',', ' ') . ' DH';
    }

    public function getFormattedAmountTtcAttribute(): string
    {
        return number_format($this->amount_ttc, 2, ',', ' ') . ' DH';
    }

    public function getFormattedWithholdingTaxAmountAttribute(): string
    {
        return number_format($this->withholding_tax_amount, 2, ',', ' ') . ' DH';
    }
}