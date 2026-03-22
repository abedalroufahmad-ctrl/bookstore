<?php

namespace App\Infrastructure\Services;

use App\Domain\Customer\Interfaces\CustomerRepositoryInterface;
use App\Models\Employee;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
class CustomerService
{
    public function __construct(
        protected CustomerRepositoryInterface $repository
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getById(string $id): ?Customer
    {
        return $this->repository->findById($id);
    }

    public function updateById(string $id, array $data): ?Customer
    {
        return $this->repository->updateById($id, $data);
    }

    public function deleteById(string $id): bool
    {
        return $this->repository->deleteById($id);
    }

    public function convertToEmployee(Customer $customer, array $payload): Employee
    {
        // New password optional: reuse customer's hash (Employee `hashed` cast skips re-hashing via Hash::isHashed).
        $passwordValue = ! empty($payload['password'] ?? null)
            ? (string) $payload['password']
            : $customer->getAuthPassword();

        $employee = Employee::create([
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'password' => $passwordValue,
            'role' => $payload['role'],
            'warehouse_id' => $payload['warehouse_id'],
            'warehouse_ids' => $payload['role'] === 'warehouse_manager'
                ? [$payload['warehouse_id']]
                : [],
        ]);

        return $employee->fresh(['warehouse']);
    }
}
