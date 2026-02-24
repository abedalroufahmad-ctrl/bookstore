<?php

namespace App\Infrastructure\Services;

use App\Domain\Author\Interfaces\AuthorRepositoryInterface;
use App\Models\Author;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuthorService
{
    public function __construct(
        protected AuthorRepositoryInterface $repository
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getById(string $id, array $with = ['books']): ?Author
    {
        return $this->repository->findById($id, $with);
    }

    public function create(array $data): Author
    {
        return $this->repository->create($data);
    }

    public function update(string $id, array $data): ?Author
    {
        $updated = $this->repository->update($id, $data);

        return $updated ? $this->repository->findById($id, ['books']) : null;
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
