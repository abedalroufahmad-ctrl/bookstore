<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Category\Interfaces\CategoryRepositoryInterface;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        protected Category $model
    ) {}

    public function findById(string $id, array $with = []): ?Category
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
                $q->where('dewey_code', 'like', "%{$search}%")
                    ->orWhere('subject_title', 'like', "%{$search}%")
                    ->orWhere('subject_number', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('dewey_code')->paginate($perPage);
    }

    public function create(array $data): Category
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
