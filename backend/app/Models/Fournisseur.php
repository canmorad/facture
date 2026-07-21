<?php

namespace App\Models;

use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fournisseur extends Model
{
    use SoftDeletes, LogsActivityTrait;

    protected $table = 'suppliers';

    protected $fillable = [
        'company_id',
        'name',
        'ice',
        'email',
        'phone',
        'address',
        'city',
        'country',
        'supplier_type',
        'tax_id',
        'subject_to_withholding_tax',
    ];

    protected $casts = [
        'subject_to_withholding_tax' => 'boolean',
    ];

    protected $attributes = [
        'supplier_type' => 'enterprise',
        'country' => 'Maroc',
        'subject_to_withholding_tax' => true,
    ];

    public function purchaseInvoices(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isSubjectToWithholdingTax(): bool
    {
        if (!$this->subject_to_withholding_tax) {
            return false;
        }

        if ($this->supplier_type === 'individual') {
            return true;
        }

        if ($this->supplier_type === 'enterprise' && empty($this->ice)) {
            return true;
        }

        return false;
    }
}