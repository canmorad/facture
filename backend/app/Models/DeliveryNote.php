<?php

namespace App\Models;

use App\Models\Traits\HasStateMachine;
use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class DeliveryNote extends Model
{
    use HasStateMachine, LogsActivityTrait;

    protected static function getTransitions(): array
    {
        return [
            'DRAFT' => ['FINALIZED'],
            'FINALIZED' => ['SENT', 'DELIVERED'],
            'SENT' => ['DELIVERED'],
            'DELIVERED' => [],
        ];
    }

    protected $fillable = [
        'status',
        'delivery_date',
        'finalized_at',
        'sent_at',
        'delivered_at',
    ];

    protected $casts = [
        'delivery_date' => 'date',
        'finalized_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }
}