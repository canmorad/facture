<?php

namespace App\Models;

use App\Models\Traits\HasStateMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasStateMachine;

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
}