<?php

namespace App\Domain\Customer\Interfaces;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CustomerRepositoryInterface
{
    public function findById(string $id): ?Customer;

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
