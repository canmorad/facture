<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CreditNote extends Model
{
    protected $fillable = [
        'type',
        'reason',
        'status',
    ];

    protected $casts = [
        'type' => 'string',
        'status' => 'string',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }
}