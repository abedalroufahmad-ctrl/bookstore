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
        $query = $this->model->newQuery()->with($filters['with'] ?? ['warehouse.publisher', 'publisher']);

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
                $this->constrainToWarehouses($query, [(string) $filters['warehouse_id']]);
            }
        }

        if (! empty($filters['warehouse_ids']) && is_array($filters['warehouse_ids'])) {
            $this->constrainToWarehouses($query, array_values(array_map('strval', $filters['warehouse_ids'])));
        }

        if (! empty($filters['filter_publisher_id'])) {
            $publisherId = (string) $filters['filter_publisher_id'];
            $warehouseIds = array_values(array_map('strval', $filters['filter_publisher_warehouse_ids'] ?? []));
            $query->where(function ($q) use ($publisherId, $warehouseIds) {
                $q->where('publisher_id', $publisherId);
                if ($warehouseIds !== []) {
                    $q->orWhere(function ($wh) use ($warehouseIds) {
                        $this->constrainToWarehouses($wh, $warehouseIds);
                    });
                }
            });
        }

        // Publisher-manager scope: peer PMs of this house, or staff whose warehouse(s) belong to it.
        if (! empty($filters['linked_publisher_id'])) {
            $publisherId = (string) $filters['linked_publisher_id'];
            $warehouseIds = array_values(array_map('strval', $filters['publisher_warehouse_ids'] ?? []));
            $query->where('role', '!=', \App\Domain\Auth\Enums\UserRole::Manager->value);
            $query->where(function ($q) use ($publisherId, $warehouseIds) {
                $q->where(function ($peer) use ($publisherId) {
                    $peer->where('role', \App\Domain\Auth\Enums\UserRole::PublisherManager->value)
                        ->where('publisher_id', $publisherId);
                });
                if (! empty($warehouseIds)) {
                    $q->orWhere(function ($staff) use ($warehouseIds) {
                        $staff->where('role', '!=', \App\Domain\Auth\Enums\UserRole::PublisherManager->value);
                        $this->constrainToWarehouses($staff, $warehouseIds);
                    });
                }
            });
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

        return $employee->fresh(['warehouse.publisher', 'publisher']);
    }

    public function delete(string $id): bool
    {
        $employee = $this->model->find($id);
        if (! $employee) {
            return false;
        }

        return (bool) $employee->delete();
    }

    public function exists(string $id): bool
    {
        return $this->model->newQuery()->whereKey($id)->exists();
    }

    /**
     * Match warehouse_id or any warehouse_ids array element (Mongo).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Employee>|\Illuminate\Database\Eloquent\Builder  $query
     * @param  list<string>  $warehouseIds
     */
    private function constrainToWarehouses($query, array $warehouseIds): void
    {
        $query->where(function ($q) use ($warehouseIds) {
            $q->whereIn('warehouse_id', $warehouseIds);
            foreach ($warehouseIds as $id) {
                $q->orWhere('warehouse_ids', $id);
            }
        });
    }
}
