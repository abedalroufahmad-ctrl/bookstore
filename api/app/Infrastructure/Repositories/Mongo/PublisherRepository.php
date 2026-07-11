<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Publisher\Interfaces\PublisherRepositoryInterface;
use App\Models\Publisher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PublisherRepository implements PublisherRepositoryInterface
{
    public function __construct(
        protected Publisher $model
    ) {}

    public function findById(string $id, array $with = []): ?Publisher
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
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Publisher
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
