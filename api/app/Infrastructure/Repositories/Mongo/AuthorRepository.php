<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Author\Interfaces\AuthorRepositoryInterface;
use App\Models\Author;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuthorRepository implements AuthorRepositoryInterface
{
    public function __construct(
        protected Author $model
    ) {}

    public function findById(string $id, array $with = []): ?Author
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
                    ->orWhere('biography', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function create(array $data): Author
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
