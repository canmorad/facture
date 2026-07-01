<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentLink extends Model
{
    protected $fillable = [
        'parent_id',
        'child_id',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'parent_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'child_id');
    }
}