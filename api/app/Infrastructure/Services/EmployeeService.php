<?php

namespace App\Infrastructure\Services;

use App\Domain\Employee\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeService
{
    public function __construct(
        protected EmployeeRepositoryInterface $repository
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getById(string $id, array $with = ['warehouse']): ?Employee
    {
        return $this->repository->findById($id, $with);
    }

    public function create(array $data): Employee
    {
        return $this->repository->create($data);
    }

    public function exists(string $id): bool
    {
        return $this->repository->exists($id);
    }
}
