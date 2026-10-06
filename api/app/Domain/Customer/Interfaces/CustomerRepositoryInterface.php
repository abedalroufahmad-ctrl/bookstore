<?php

namespace App\Domain\Customer\Interfaces;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    public function findById(string $id): ?Customer;

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function updateById(string $id, array $data): ?Customer;

    public function deleteById(string $id): bool;
}
