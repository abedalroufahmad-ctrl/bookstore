<?php

namespace App\Infrastructure\Services;

use App\Domain\Warehouse\Interfaces\WarehouseRepositoryInterface;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WarehouseService
{
    public function __construct(
        protected WarehouseRepositoryInterface $repository
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getById(string $id, array $with = ['employees', 'books']): ?Warehouse
    {
        return $this->repository->findById($id, $with);
    }

    public function create(array $data): Warehouse
    {
        return $this->repository->create($data);
    }

    public function update(string $id, array $data): ?Warehouse
    {
        $updated = $this->repository->update($id, $data);

        return $updated ? $this->repository->findById($id, ['employees', 'books']) : null;
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
