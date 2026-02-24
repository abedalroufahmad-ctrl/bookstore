<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Warehouse\Interfaces\WarehouseRepositoryInterface;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WarehouseRepository implements WarehouseRepositoryInterface
{
    public function __construct(
        protected Warehouse $model
    ) {}

    public function findById(string $id, array $with = []): ?Warehouse
    {
        $query = $this->model->newQuery();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->find($id);
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->newQuery();

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (! empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Warehouse
    {
        return $this->model->create($data);
    }

    public function update(string $id, array $data): bool
    {
        $model = $this->findById($id);

        if (! $model) {
            return false;
        }

        return $model->update($data);
    }

    public function delete(string $id): bool
    {
        $model = $this->findById($id);

        if (! $model) {
            return false;
        }

        return $model->delete();
    }
}
