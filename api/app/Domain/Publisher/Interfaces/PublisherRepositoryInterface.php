<?php

namespace App\Domain\Publisher\Interfaces;

use App\Models\Publisher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PublisherRepositoryInterface
{
    public function findById(string $id, array $with = []): ?Publisher;

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Publisher;

    public function update(string $id, array $data): bool;

    public function delete(string $id): bool;
}
