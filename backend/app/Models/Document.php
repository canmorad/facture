<?php

namespace App\Models;

use App\Exceptions\ImmutableDocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
    ];

    protected $casts = [
        'total_ht' => 'float',
        'total_tva' => 'float',
        'total_ttc' => 'float',
        'global_discount_value' => 'float',
        'global_discount_amount' => 'float',
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
        if ($this->parent_document_id) {
            throw new ImmutableDocumentException(
                $this->source_type ?? 'source',
                $this->id
            );
        }
    }

}