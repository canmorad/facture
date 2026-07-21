<?php

namespace App\Models;

use App\Models\Traits\HasStateMachine;
use App\Traits\LogsActivityTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasStateMachine, LogsActivityTrait;

    protected static function getTransitions(): array
    {
        return [
            'DRAFT' => ['FINALIZED'],
            'FINALIZED' => ['SENT', 'SIGNED', 'EXPIRED'],
            'SENT' => ['SIGNED', 'EXPIRED'],
            'SIGNED' => [],
            'EXPIRED' => [],
        ];
    }

    protected $fillable = [
        'status',
        'valid_until',
        'finalized_at',
        'sent_at',
        'signed_at',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'finalized_at' => 'datetime',
        'sent_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }
}