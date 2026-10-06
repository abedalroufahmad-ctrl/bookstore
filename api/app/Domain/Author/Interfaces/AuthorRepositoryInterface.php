<?php

namespace App\Domain\Author\Interfaces;

use App\Models\Author;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AuthorRepositoryInterface
{
    public function findById(string $id, array $with = []): ?Author;

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Author;

    public function update(string $id, array $data): bool;

    public function delete(string $id): bool;
}
