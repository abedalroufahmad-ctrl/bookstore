<?php

namespace App\Domain\Warehouse\Interfaces;

use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface WarehouseRepositoryInterface
{
    public function findById(string $id, array $with = []): ?Warehouse;

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Warehouse;

    public function update(string $id, array $data): bool;

    public function delete(string $id): bool;
}
