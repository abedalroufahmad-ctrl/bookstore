<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Employee\Interfaces\EmployeeRepositoryInterface;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EmployeeRepository implements EmployeeRepositoryInterface
{
    public function __construct(
        protected Employee $model
    ) {}

    public function findById(string $id, array $with = []): ?Employee
    {
        $query = $this->model->newQuery();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->find($id);
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery()->with($filters['with'] ?? ['warehouse']);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (array_key_exists('warehouse_id', $filters) && $filters['warehouse_id'] !== null && $filters['warehouse_id'] !== '') {
            if ($filters['warehouse_id'] === '__none__') {
                $query->whereIn('warehouse_id', []);
            } else {
                $query->where('warehouse_id', $filters['warehouse_id']);
            }
        }

        if (! empty($filters['warehouse_ids']) && is_array($filters['warehouse_ids'])) {
            $query->whereIn('warehouse_id', $filters['warehouse_ids']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Employee
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): ?Employee
    {
        $employee = $this->model->find($id);
        if (! $employee) {
            return null;
        }
        $employee->update($data);
        return $employee->fresh(['warehouse']);
    }

    public function exists(string $id): bool
    {
        return $this->model->newQuery()->whereKey($id)->exists();
    }
}
