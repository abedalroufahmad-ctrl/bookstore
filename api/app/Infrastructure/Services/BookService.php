<?php

namespace App\Infrastructure\Services;

use App\Domain\Book\Interfaces\BookRepositoryInterface;
use App\Models\Book;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookService
{
    public function __construct(
        protected BookRepositoryInterface $repository
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filters['with'] = $filters['with'] ?? ['category', 'warehouse', 'authors'];

        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getById(string $id, array $with = ['category', 'warehouse', 'authors']): ?Book
    {
        return $this->repository->findById($id, $with);
    }

    public function create(array $data): Book
    {
        return $this->repository->create($data);
    }

    public function update(string $id, array $data): ?Book
    {
        $updated = $this->repository->update($id, $data);

        return $updated ? $this->repository->findById($id, ['category', 'warehouse', 'authors']) : null;
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
