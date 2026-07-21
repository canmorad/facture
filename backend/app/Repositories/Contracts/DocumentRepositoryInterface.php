<?php

namespace App\Repositories\Contracts;

use App\Models\Document;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface DocumentRepositoryInterface
{
    public function find(int $id): ?Document;
    public function findWithRelations(int $id, array $relations = []): ?Document;
    public function getByCompany(int $companyId, array $filters = [], array $relations = []): Collection;
    public function paginateByCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getByType(int $companyId, string $documentableType, array $filters = []): Collection;
    public function getByStatus(int $companyId, string $status): Collection;
    public function search(int $companyId, string $query, array $filters = []): Collection;
    public function getByDateRange(int $companyId, string $startDate, string $endDate, array $filters = []): Collection;
    public function getByCustomer(int $companyId, int $customerId): Collection;
    public function getChildren(int $documentId, ?string $type = null): Collection;
    public function getParentChain(int $documentId): Collection;
    public function lock(Document $document, string $reason, ?int $userId = null): void;
    public function isLocked(Document $document): bool;
    public function getDefaultRelations(): array;
}
