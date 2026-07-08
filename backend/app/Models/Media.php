<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Media extends Model
{
    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
    ];

    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}