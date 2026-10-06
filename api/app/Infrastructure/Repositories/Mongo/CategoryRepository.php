<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Category\Interfaces\CategoryRepositoryInterface;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
                    ->orWhere('subject_title_en', 'like', "%{$search}%")
                    ->orWhere('subject_title_ar', 'like', "%{$search}%")
                    ->orWhere('subject_number', 'like', "%{$search}%");
            });
        }

        $paginator = $query->orderBy('dewey_code')->paginate($perPage);

        if (! empty($filters['with_books_count'])) {
            $this->attachBooksCounts($paginator->getCollection());
        }

        return $paginator;
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

    /**
     * One aggregation instead of N book-list requests for category book counts.
     */
    private function attachBooksCounts($categories): void
    {
        $ids = $categories->map(fn (Category $c) => (string) $c->getKey())->filter()->values()->all();
        if ($ids === []) {
            return;
        }

        $rows = DB::connection('mongodb')
            ->getCollection((new Book)->getTable())
            ->aggregate([
                ['$match' => ['category_id' => ['$in' => $ids]]],
                ['$group' => ['_id' => '$category_id', 'count' => ['$sum' => 1]]],
            ]);

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['_id']] = (int) $row['count'];
        }

        foreach ($categories as $category) {
            $category->setAttribute('books_count', $counts[(string) $category->getKey()] ?? 0);
        }
    }
}
