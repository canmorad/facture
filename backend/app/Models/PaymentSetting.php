<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'default_cash_register_id',
        'auto_mark_invoice_paid',
        'allow_partial_payments',
        'allow_overpayment',
    ];

    protected $casts = [
        'auto_mark_invoice_paid' => 'boolean',
        'allow_partial_payments' => 'boolean',
        'allow_overpayment' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultCashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class, 'default_cash_register_id');
    }

    /**
     * Get payment settings for a company, creating defaults if not exists
     */
    public static function getForCompany(int $companyId): self
    {
        return static::firstOrCreate(
            ['company_id' => $companyId],
            [
                'auto_mark_invoice_paid' => true,
                'allow_partial_payments' => true,
                'allow_overpayment' => false,
            ]
        );
    }

    /**
     * Check if overpayment is allowed for a company
     */
    public static function isOverpaymentAllowed(int $companyId): bool
    {
        $settings = static::where('company_id', $companyId)->first();
        return $settings?->allow_overpayment ?? false;
    }

    /**
     * Check if partial payments are allowed for a company
     */
    public static function arePartialPaymentsAllowed(int $companyId): bool
    {
        $settings = static::where('company_id', $companyId)->first();
        return $settings?->allow_partial_payments ?? true;
    }

    /**
     * Check if invoice should be auto-marked as paid for a company
     */
    public static function shouldAutoMarkInvoicePaid(int $companyId): bool
    {
        $settings = static::where('company_id', $companyId)->first();
        return $settings?->auto_mark_invoice_paid ?? true;
    }

    /**
     * Get default cash register for a company
     */
    public static function getDefaultCashRegister(int $companyId): ?int
    {
        $settings = static::where('company_id', $companyId)->first();
        return $settings?->default_cash_register_id;
    }
}
