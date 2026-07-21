<?php

namespace App\Repositories;

use App\Models\Document;
use App\Repositories\Contracts\DocumentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DocumentRepository implements DocumentRepositoryInterface
{
    protected array $defaultRelations = [
        'customer.customerable',
        'items',
        'documentable',
        'parent',
        'parent.documentable',
        'parent.customer',
    ];

    protected array $minimalRelations = [
        'customer.customerable',
        'documentable',
    ];

    public function find(int $id): ?Document
    {
        return Document::find($id);
    }

    public function findWithRelations(int $id, array $relations = []): ?Document
    {
        $query = Document::query();

        if (empty($relations)) {
            $query->with($this->defaultRelations);
        } else {
            $query->with($relations);
        }

        return $query->find($id);
    }

    public function getByCompany(int $companyId, array $filters = [], array $relations = []): Collection
    {
        return $this->buildQuery($companyId, $filters, $relations)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function paginateByCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->buildQuery($companyId, $filters, $this->minimalRelations)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    public function getByType(int $companyId, string $documentableType, array $filters = []): Collection
    {
        return $this->buildQuery($companyId, array_merge($filters, ['type' => $documentableType]))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getByStatus(int $companyId, string $status): Collection
    {
        return Document::where('company_id', $companyId)
            ->whereHas('documentable', function (Builder $query) use ($status) {
                $query->where('status', $status);
            })
            ->with($this->minimalRelations)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function search(int $companyId, string $query, array $filters = []): Collection
    {
        return $this->buildQuery($companyId, $filters, $this->minimalRelations)
            ->where(function (Builder $q) use ($query) {
                $q->where('number', 'like', "%{$query}%")
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($query) {
                        $customerQuery->whereHas('customerable', function (Builder $polymorphicQuery) use ($query) {
                            $polymorphicQuery->where('legal_name', 'like', "%{$query}%")
                                ->orWhere('name', 'like', "%{$query}%");
                        })->orWhere('email', 'like', "%{$query}%");
                    })
                    ->orWhere('total_ht', 'like', "%{$query}%")
                    ->orWhere('total_ttc', 'like', "%{$query}%");
            })
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getByDateRange(int $companyId, string $startDate, string $endDate, array $filters = []): Collection
    {
        return $this->buildQuery($companyId, $filters, $this->minimalRelations)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getByCustomer(int $companyId, int $customerId): Collection
    {
        return Document::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->with($this->minimalRelations)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getChildren(int $documentId, ?string $type = null): Collection
    {
        $query = Document::where('parent_document_id', $documentId);

        if ($type) {
            $query->where('documentable_type', $type);
        }

        return $query->with(['documentable', 'customer'])->get();
    }

    public function getParentChain(int $documentId): Collection
    {
        $chain = collect();
        $current = $this->find($documentId);

        while ($current && $current->parent_document_id) {
            $parent = $this->findWithRelations($current->parent_document_id, ['documentable', 'customer']);
            if (!$parent) break;

            $chain->push($parent);
            $current = $parent;

            if ($chain->count() > 50) break;
        }

        return $chain->reverse();
    }

    public function lock(Document $document, string $reason, ?int $userId = null): void
    {
        $document->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $userId,
            'lock_reason' => $reason,
        ]);
    }

    public function isLocked(Document $document): bool
    {
        return $document->is_locked ?? false;
    }

    public function getDefaultRelations(): array
    {
        return $this->defaultRelations;
    }

    protected function buildQuery(int $companyId, array $filters = [], array $relations = []): Builder
    {
        $query = Document::where('company_id', $companyId);

        if (empty($relations)) {
            $query->with($this->defaultRelations);
        } else {
            $query->with($relations);
        }

        foreach ($filters as $key => $value) {
            if ($value === null) continue;

            match ($key) {
                'type' => $query->where('documentable_type', $value),
                'status' => $query->whereHas('documentable', function (Builder $q) use ($value) {
                    $q->where('status', $value);
                }),
                'customer_id' => $query->where('customer_id', $value),
                'date_from' => $query->where('created_at', '>=', $value),
                'date_to' => $query->where('created_at', '<=', $value),
                'number' => $query->where('number', 'like', "%{$value}%"),
                'min_total' => $query->where('total_ttc', '>=', $value),
                'max_total' => $query->where('total_ttc', '<=', $value),
                'is_locked' => $query->where('is_locked', $value === '1' || $value === true),
                'invoice_type' => $query->where('documentable_type', \App\Models\Invoice::class)
                    ->whereHas('documentable', function (Builder $q) use ($value) {
                        $q->where('type', $value);
                    }),
                default => null,
            };
        }

        return $query;
    }
}
