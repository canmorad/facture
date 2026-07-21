<?php

namespace App\Models;

use App\Contracts\PayableInterface;
use App\Models\Traits\HasStateMachine;
use App\Traits\LogsActivityTrait;
use App\Traits\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Deposit extends Model implements PayableInterface
{
    use HasStateMachine, Payable, LogsActivityTrait;

    protected static function getTransitions(): array
    {
        return [
            'DRAFT' => ['FINALIZED'],
            'FINALIZED' => ['PAID', 'CANCELLED'],
            'PAID' => [],
            'CANCELLED' => [],
        ];
    }

    protected $fillable = [
        'company_id',
        'quote_id',
        'status',
        'input_type',
        'input_value',
        'finalized_at',
        'paid_at',
    ];

    protected $casts = [
        'input_value' => 'float',
        'finalized_at' => 'datetime',
        'paid_at' => 'datetime',
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
        return ['FINALIZED'];
    }

    /**
     * Override markAsPaid for Deposit specific logic
     */
    public function markAsPaid(): void
    {
        $this->setStatus('PAID');
        $this->setAttribute('paid_at', now());
        $this->save();
    }
}