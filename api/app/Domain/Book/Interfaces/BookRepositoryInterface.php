<?php

namespace App\Domain\Book\Interfaces;

use App\Models\Book;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BookRepositoryInterface
{
    public function findById(string $id, array $with = []): ?Book;

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Book;

    public function update(string $id, array $data): bool;

    public function delete(string $id): bool;
}
