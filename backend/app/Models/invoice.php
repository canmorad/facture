<?php

namespace App\Models;

use App\Contracts\PayableInterface;
use App\Models\Traits\HasStateMachine;
use App\Traits\LogsActivityTrait;
use App\Traits\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model implements PayableInterface
{
    use HasStateMachine, Payable, LogsActivityTrait;

    protected static function getTransitions(): array
    {
        return [
            'DRAFT' => ['FINALIZED'],
            'FINALIZED' => ['SENT', 'PAID'],
            'SENT' => ['PAID', 'OVERDUE', 'CANCELLED'],
            'PAID' => [],
            'OVERDUE' => ['PAID', 'CANCELLED'],
            'CANCELLED' => [],
        ];
    }

    protected $fillable = [
        'status',
        'due_date',
        'type',
        'finalized_at',
        'sent_at',
        'paid_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'finalized_at' => 'datetime',
        'sent_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(InvoiceDeduction::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /**
     * PayableInterface implementation
     */
    public function getPaymentEligibleStatuses(): array
    {
        return ['FINALIZED', 'SENT', 'OVERDUE'];
    }

    /**
     * Override getTotalDeductions to include invoice-specific deductions
     */
    public function getTotalDeductions(): float
    {
        return (float) $this->deductions()->sum('amount');
    }

    /**
     * Override markAsPaid for Invoice specific logic
     */
    public function markAsPaid(): void
    {
        $this->setStatus('PAID');
        $this->setAttribute('paid_at', now());
        $this->save();
    }

    /**
     * Override markAsSent for Invoice
     */
    public function markAsSent(): void
    {
        $this->setStatus('SENT');
        $this->setAttribute('sent_at', now());
        $this->save();
    }

    /**
     * Legacy accessor for backward compatibility
     */
    public function getTotalPaidAmountAttribute(): float
    {
        return (float) $this->completedPayments()->sum('amount');
    }

    /**
     * Legacy accessor for backward compatibility
     */
    public function getRemainingAmountAttribute(): float
    {
        return $this->getRemainingAmount();
    }
}