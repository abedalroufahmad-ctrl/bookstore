<?php

namespace App\Domain\Employee\Interfaces;

use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface EmployeeRepositoryInterface
{
    public function findById(string $id, array $with = []): ?Employee;

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): Employee;

    public function update(string $id, array $data): ?Employee;

    public function exists(string $id): bool;
}
