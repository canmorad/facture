<?php

namespace App\Models;

use App\Models\Traits\HasStateMachine;
use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proforma extends Model
{
    use HasStateMachine, LogsActivityTrait;

    protected static function getTransitions(): array
    {
        return [
            'DRAFT' => ['FINALIZED', 'CANCELLED'],
            'FINALIZED' => ['SENT', 'CONVERTED', 'EXPIRED', 'CANCELLED'],
            'SENT' => ['CONVERTED', 'EXPIRED', 'CANCELLED'],
            'CONVERTED' => [],
            'EXPIRED' => ['CONVERTED', 'CANCELLED'],
            'CANCELLED' => [],
        ];
    }

    protected $fillable = [
        'status',
        'validity_date',
        'converted_to_invoice_id',
        'finalized_at',
        'sent_at',
        'converted_at',
        'expired_at',
        'cancelled_at',
    ];

    protected $casts = [
        'validity_date' => 'date',
        'finalized_at' => 'datetime',
        'sent_at' => 'datetime',
        'converted_at' => 'datetime',
        'expired_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }

    public function convertedInvoice(): HasMany
    {
        return $this->hasMany(Invoice::class, 'id', 'converted_to_invoice_id');
    }

    public function isExpired(): bool
    {
        return $this->validity_date && $this->validity_date->isPast() &&
               in_array($this->status, ['FINALIZED', 'SENT']);
    }

    public function canBeConverted(): bool
    {
        return in_array($this->status, ['FINALIZED', 'SENT', 'EXPIRED']);
    }

    public function hasBeenConverted(): bool
    {
        return $this->status === 'CONVERTED' || !is_null($this->converted_to_invoice_id);
    }
}
