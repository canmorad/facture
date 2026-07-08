<?php

namespace App\Models;

use App\Models\Traits\HasStateMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class PurchaseOrder extends Model
{
    use HasStateMachine;

    protected static function getTransitions(): array
    {
        return [
            'DRAFT' => ['FINALIZED'],
            'FINALIZED' => ['SENT', 'CONFIRMED'],
            'SENT' => ['CONFIRMED', 'CANCELLED'],
            'CONFIRMED' => [],
            'CANCELLED' => [],
        ];
    }

    protected $fillable = [
        'status',
        'expected_date',
        'finalized_at',
        'sent_at',
        'confirmed_at',
    ];

    protected $casts = [
        'expected_date' => 'date',
        'finalized_at' => 'datetime',
        'sent_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }
}