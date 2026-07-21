<?php

namespace App\Models;

use App\Exceptions\ImmutableDocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\LogsActivityTrait;

class Document extends Model
{
    use LogsActivityTrait;

    protected $fillable = [
        'company_id',
        'customer_id',
        'bank_account_id',
        'parent_document_id',
        'number',
        'total_ht',
        'total_tva',
        'total_ttc',
        'global_discount_type',
        'global_discount_value',
        'global_discount_amount',
        'notes',
        'terms',
        'intro_text',
        'footer_text',
        'conclusion_text',
        'documentable_type',
        'documentable_id',
        'payment_condition',
        'payment_mode',
        'late_fee_interest',
        'is_locked',
        'locked_at',
        'locked_by',
        'lock_reason',
    ];

    protected $casts = [
        'total_ht' => 'float',
        'total_tva' => 'float',
        'total_ttc' => 'float',
        'global_discount_value' => 'float',
        'global_discount_amount' => 'float',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'parent_document_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(__CLASS__, 'parent_document_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class);
    }

    public function getSourceTypeAttribute(): ?string
    {
        if (!$this->parent_document_id || !$this->parent) {
            return null;
        }

        return class_basename($this->parent->documentable_type);
    }

    public function isDerivedFromQuote(): bool
    {
        $ancestor = $this->findSourceDocument();

        if (!$ancestor) {
            return false;
        }

        return $ancestor->documentable_type === Quote::class;
    }

    public function findSourceDocument(): ?self
    {
        $current = $this;

        $visited = [];

        while ($current->parent_document_id && $current->parent) {
            if (in_array($current->id, $visited)) {
                break;
            }
            $visited[] = $current->id;
            $current = $current->parent;
        }

        if ($current->id === $this->id && !$current->parent_document_id) {
            return null;
        }

        return $current;
    }

    public function getAncestorChain(): array
    {
        $chain = [];
        $current = $this;
        $visited = [];

        $chain[] = $this->buildChainEntry($current);

        while ($current->parent_document_id && $current->parent) {
            if (in_array($current->parent->id, $visited)) {
                break;
            }
            $visited[] = $current->parent->id;
            $current = $current->parent;
            array_unshift($chain, $this->buildChainEntry($current));
        }

        return $chain;
    }

    protected function buildChainEntry(Document $doc): array
    {
        $customerName = null;
        if ($doc->customer) {
            $customerable = $doc->customer->customerable;
            if ($customerable && $doc->customer->type === 'b2b') {
                $customerName = $customerable->legal_name ?? $doc->customer->name;
            } elseif ($customerable && $doc->customer->type === 'b2c') {
                $customerName = $customerable->name ?? $doc->customer->name;
            } else {
                $customerName = $doc->customer->name;
            }
        }

        return [
            'id' => $doc->id,
            'documentable_type' => class_basename($doc->documentable_type),
            'number' => $doc->number,
            'status' => $doc->documentable->status ?? null,
            'total_ttc' => $doc->total_ttc,
            'customer' => $customerName ? ['name' => $customerName] : null,
        ];
    }

    public function getDescendantChain(): array
    {
        $chain = [];
        $this->collectDescendants($chain);

        return $chain;
    }

    protected function collectDescendants(array &$chain): void
    {
        foreach ($this->children as $child) {
            $chain[] = $this->buildChainEntry($child);
            $child->collectDescendants($chain);
        }
    }

    public function hasChildrenOfType(string $type): bool
    {
        return $this->children()
            ->where('documentable_type', $type)
            ->exists();
    }

    public function getChildOfType(string $type): ?self
    {
        return $this->children()
            ->where('documentable_type', $type)
            ->first();
    }

    public function guardImmutable(): void
    {
        if ($this->is_locked) {
            throw new ImmutableDocumentException(
                $this->source_type ?? 'source',
                $this->id
            );
        }

        if ($this->parent_document_id) {
            throw new ImmutableDocumentException(
                $this->source_type ?? 'source',
                $this->id
            );
        }

        if ($this->documentable_type && $this->documentable) {
            $status = $this->documentable->status ?? null;
            $immutableStatuses = ['FINALIZED', 'SENT', 'PAID', 'CANCELLED', 'SIGNED'];
            if (in_array($status, $immutableStatuses)) {
                throw new ImmutableDocumentException(
                    class_basename($this->documentable_type),
                    $this->id
                );
            }
        }
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with(['customer.customerable', 'items', 'documentable', 'parent']);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->whereHas('documentable', function ($q) use ($status) {
            $q->where('status', $status);
        });
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('documentable_type', $type);
    }

    public function scopeInDateRange(Builder $query, $startDate, $endDate): Builder
    {
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        return $query;
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('number', 'like', "%{$search}%")
                ->orWhereHas('customer', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('items', function ($iq) use ($search) {
                    $iq->where('description', 'like', "%{$search}%");
                });
        });
    }

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where('is_locked', true);
    }

    public function scopeUnlocked(Builder $query): Builder
    {
        return $query->where('is_locked', false);
    }

    public function workflowHistories(): HasMany
    {
        return $this->hasMany(DocumentWorkflowHistory::class);
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(DocumentRelationship::class, 'parent_document_id');
    }

    public function childRelationships(): HasMany
    {
        return $this->hasMany(DocumentRelationship::class, 'child_document_id');
    }

    public function getAllRelationships(): HasMany
    {
        return $this->relationships()->union($this->childRelationships());
    }

    public function lock(string $reason = null, int $userId = null): void
    {
        $this->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $userId,
            'lock_reason' => $reason,
        ]);
    }

    public function unlock(): void
    {
        $this->update([
            'is_locked' => false,
            'locked_at' => null,
            'locked_by' => null,
            'lock_reason' => null,
        ]);
    }

    public function isFinalized(): bool
    {
        if (!$this->documentable || !$this->documentable->status) {
            return false;
        }

        return in_array($this->documentable->status, ['FINALIZED', 'SENT', 'PAID', 'CANCELLED', 'SIGNED', 'DELIVERED', 'CONFIRMED', 'APPLIED', 'EXPIRED']);
    }

    public function isImmutable(): bool
    {
        if ($this->is_locked) {
            return true;
        }

        if ($this->parent_document_id) {
            return true;
        }

        return $this->isFinalized();
    }

    public function getAllocatedAmount(): float
    {
        return $this->childRelationships()
            ->whereNotNull('allocated_amount')
            ->sum('allocated_amount');
    }

    public function getRemainingAmountForAllocation(): float
    {
        $allocated = $this->getAllocatedAmount();
        return max(0, $this->total_ttc - $allocated);
    }

    public function getAllocationPercentage(): ?float
    {
        if ($this->total_ttc <= 0) {
            return null;
        }

        $allocated = $this->getAllocatedAmount();
        return round(($allocated / $this->total_ttc) * 100, 2);
    }

    public function getConversionProgress(): array
    {
        $childTypes = [
            Invoice::class => 'invoices',
            Proforma::class => 'proformas',
            PurchaseOrder::class => 'purchase_orders',
            DeliveryNote::class => 'delivery_notes',
        ];

        $progress = [];
        foreach ($childTypes as $type => $key) {
            $children = $this->children()->where('documentable_type', $type)->get();
            $totalAllocated = $children->sum('total_ttc');
            $count = $children->count();

            $progress[$key] = [
                'count' => $count,
                'allocated_amount' => $totalAllocated,
                'percentage' => $this->total_ttc > 0 ? round(($totalAllocated / $this->total_ttc) * 100, 2) : 0,
            ];
        }

        return $progress;
    }

    public function hasDescendantOfType(string $type): bool
    {
        return $this->children()
            ->where('documentable_type', $type)
            ->exists();
    }

    public function hasAncestorOfType(string $type): bool
    {
        $current = $this;
        $visited = [];

        while ($current->parent_document_id && $current->parent) {
            if (in_array($current->id, $visited)) {
                break;
            }
            $visited[] = $current->id;
            $current = $current->parent;

            if ($current->documentable_type === $type) {
                return true;
            }
        }

        return false;
    }

    public function getFirstAncestorOfType(string $type): ?self
    {
        $current = $this;
        $visited = [];

        while ($current->parent_document_id && $current->parent) {
            if (in_array($current->id, $visited)) {
                break;
            }
            $visited[] = $current->id;
            $current = $current->parent;

            if ($current->documentable_type === $type) {
                return $current;
            }
        }

        return null;
    }

    public function getRelatedDocumentsByType(string $type): array
    {
        $parents = [];
        $children = [];

        $current = $this;
        $visited = [];

        while ($current->parent_document_id && $current->parent) {
            if (in_array($current->id, $visited)) {
                break;
            }
            $visited[] = $current->id;
            $current = $current->parent;

            if ($current->documentable_type === $type) {
                $parents[] = $current;
            }
        }

        $children = $this->children()
            ->where('documentable_type', $type)
            ->get()
            ->toArray();

        return [
            'parents' => $parents,
            'children' => $children,
        ];
    }

    public function getWorkflowSummary(): array
    {
        return [
            'id' => $this->id,
            'type' => class_basename($this->documentable_type),
            'number' => $this->number,
            'status' => $this->documentable?->getAttribute('status'),
            'total_ttc' => $this->total_ttc,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer?->name,
            'has_parent' => !is_null($this->parent_document_id),
            'has_children' => $this->children()->exists(),
            'is_locked' => $this->is_locked,
            'allocation' => [
                'allocated' => $this->getAllocatedAmount(),
                'remaining' => $this->getRemainingAmountForAllocation(),
                'percentage' => $this->getAllocationPercentage(),
            ],
            'conversion_progress' => $this->getConversionProgress(),
        ];
    }
}