<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentWorkflowHistory extends Model
{
    protected $fillable = [
        'document_id',
        'event',
        'from_status',
        'to_status',
        'metadata',
        'triggered_by',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForDocument($query, $documentId)
    {
        return $query->where('document_id', $documentId);
    }

    public function scopeByEvent($query, $event)
    {
        return $query->where('event', $event);
    }
}
