<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Quote extends Model
{
    protected $fillable = [
        'status',
        'valid_until',
    ];

    protected $casts = [
        'valid_until' => 'date',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }
}