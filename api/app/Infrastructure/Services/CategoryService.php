<?php

namespace App\Infrastructure\Services;

use App\Domain\Category\Interfaces\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryService
{
    public function __construct(
        protected CategoryRepositoryInterface $repository
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getById(string $id, array $with = ['books']): ?Category
    {
        return $this->repository->findById($id, $with);
    }

    public function create(array $data): Category
    {
        return $this->repository->create($data);
    }

    public function update(string $id, array $data): ?Category
    {
        $updated = $this->repository->update($id, $data);

        return $updated ? $this->repository->findById($id, ['books']) : null;
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
