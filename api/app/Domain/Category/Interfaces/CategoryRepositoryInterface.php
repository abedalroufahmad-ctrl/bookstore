<?php

namespace App\Domain\Category\Interfaces;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CategoryRepositoryInterface
{
    public function findById(string $id, array $with = []): ?Category;

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Category;

    public function update(string $id, array $data): bool;

    public function delete(string $id): bool;
}
