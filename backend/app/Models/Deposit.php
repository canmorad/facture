<?php

namespace App\Models;

use App\Models\Traits\HasStateMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    use HasStateMachine;

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
}