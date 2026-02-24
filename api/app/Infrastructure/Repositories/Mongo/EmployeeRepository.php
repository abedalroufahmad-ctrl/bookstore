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
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Employee
    {
        return $this->model->create($data);
    }

    public function exists(string $id): bool
    {
        return $this->model->newQuery()->whereKey($id)->exists();
    }
}
