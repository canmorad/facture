<?php

namespace App\Models;

use App\Models\Traits\HasStateMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CreditNote extends Model
{
    use HasStateMachine;

    protected static function getTransitions(): array
    {
        return [
            'DRAFT' => ['FINALIZED'],
            'FINALIZED' => ['SENT'],
            'SENT' => ['APPLIED'],
            'APPLIED' => [],
        ];
    }

    protected $fillable = [
        'type',
        'reason',
        'status',
        'finalized_at',
        'sent_at',
        'applied_at',
    ];

    protected $casts = [
        'finalized_at' => 'datetime',
        'sent_at' => 'datetime',
        'applied_at' => 'datetime',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }
}