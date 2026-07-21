<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberingSerie extends Model
{
    protected $fillable = [
        'company_id',
        'format',
        'min_size',
        'reset_period',
        'start_from_invoice',
        'start_from_quote',
        'start_from_credit_note',
        'start_from_deposit_invoice',
        'start_from_deposit_credit_note',
        'start_from_delivery_note',
        'start_from_purchase_order',
        'start_from_proforma',
        'current_invoice',
        'current_quote',
        'current_credit_note',
        'current_deposit_invoice',
        'current_deposit_credit_note',
        'current_delivery_note',
        'current_purchase_order',
        'current_proforma',
        'start_from_balance_invoice',
        'current_balance_invoice',
        'start_from_bank_remittance',
        'current_bank_remittance',
    ];

    protected $casts = [
        'min_size' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}