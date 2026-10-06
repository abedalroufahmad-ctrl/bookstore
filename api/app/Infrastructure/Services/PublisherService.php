<?php

namespace App\Infrastructure\Services;

use App\Domain\Publisher\Interfaces\PublisherRepositoryInterface;
use App\Models\Publisher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublisherService
{
    public function __construct(
        protected PublisherRepositoryInterface $repository
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getById(string $id, array $with = []): ?Publisher
    {
        return $this->repository->findById($id, $with);
    }

    public function create(array $data): Publisher
    {
        return $this->repository->create($data);
    }

    public function update(string $id, array $data): ?Publisher
    {
        $updated = $this->repository->update($id, $data);

        return $updated ? $this->repository->findById($id) : null;
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
