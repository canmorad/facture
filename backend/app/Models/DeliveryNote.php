<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class DeliveryNote extends Model
{
    protected $fillable = [
        'status',
        'delivery_date',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function document(): MorphOne
    {
        return $this->morphOne(Document::class, 'documentable');
    }
}