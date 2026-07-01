<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Invoice extends Model
{
    protected $fillable = [
        'status',
        'due_date',
        'paid_at',
        'type',
        'deposit_input_type',
        'deposit_input_value',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'deposit_input_value' => 'float',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }
}