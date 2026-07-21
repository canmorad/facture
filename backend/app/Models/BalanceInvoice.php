<?php

namespace App\Models;

use App\Contracts\PayableInterface;
use App\Traits\LogsActivityTrait;
use App\Traits\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class BalanceInvoice extends Model implements PayableInterface
{
    use Payable, LogsActivityTrait;

    protected $fillable = [
        'company_id',
        'quote_id',
        'status',
        'input_type',
        'input_value',
        'deposit_ids',
        'calculated_balance',
    ];

    protected $casts = [
        'input_value' => 'float',
        'calculated_balance' => 'float',
        'deposit_ids' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }

    /**
     * PayableInterface implementation
     */
    public function getPaymentEligibleStatuses(): array
    {
        return ['FINALIZED', 'SENT', 'OVERDUE'];
    }

    /**
     * Override getTotalAmount for BalanceInvoice specific logic
     * Uses calculated_balance instead of document total
     */
    public function getTotalAmount(): float
    {
        return (float) $this->calculated_balance;
    }

    /**
     * Override markAsPaid for BalanceInvoice specific logic
     */
    public function markAsPaid(): void
    {
        $this->setStatus('PAID');
        $this->save();
    }

    /**
     * Override markAsSent for BalanceInvoice
     */
    public function markAsSent(): void
    {
        $this->setStatus('SENT');
        $this->save();
    }
}
