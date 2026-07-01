<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Deposit extends Model
{
    protected $fillable = [
        'company_id',
        'quote_id',
        'status',
        'input_type',
        'input_value',
    ];

    protected $casts = [
        'input_value' => 'float',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
    
}