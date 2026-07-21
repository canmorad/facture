<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRelationship extends Model
{
    protected $fillable = [
        'parent_document_id',
        'child_document_id',
        'relationship_type',
        'allocated_amount',
        'allocated_quantity',
    ];

    protected $casts = [
        'allocated_amount' => 'float',
        'allocated_quantity' => 'float',
    ];

    public function parentDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'parent_document_id');
    }

    public function childDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'child_document_id');
    }

    public function scopeForParent($query, $parentId)
    {
        return $query->where('parent_document_id', $parentId);
    }

    public function scopeForChild($query, $childId)
    {
        return $query->where('child_document_id', $childId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('relationship_type', $type);
    }

    public function scopeByTypes($query, array $types)
    {
        return $query->whereIn('relationship_type', $types);
    }

    public function scopeWithAllocations($query)
    {
        return $query->whereNotNull('allocated_amount')
            ->where('allocated_amount', '>', 0);
    }

    public function getAllocationPercentageAttribute(): ?float
    {
        if (!$this->allocated_amount || !$this->parentDocument) {
            return null;
        }

        $parentTotal = $this->parentDocument->total_ttc;
        if ($parentTotal <= 0) {
            return null;
        }

        return round(($this->allocated_amount / $parentTotal) * 100, 2);
    }
}
