<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Author\Interfaces\AuthorRepositoryInterface;
use App\Models\Author;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Cache;

class AuthorRepository implements AuthorRepositoryInterface
{
    private const COUNT_CACHE_TTL = 600;

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
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                // Name-only search keeps author listing fast at 100k+ scale.
                $query->where('name', 'like', "%{$search}%");
            }
        }

        $page = max(1, (int) (request()->get('page') ?: 1));
        $total = $this->cachedTotal(clone $query, $filters);

        $items = (clone $query)
            ->orderBy('name')
            ->forPage($page, $perPage)
            ->get();

        return new Paginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
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

    private function cachedTotal($query, array $filters): int
    {
        $version = (int) Cache::get('bookstore_catalog_version', 0);
        $key = 'bookstore_authors_total_v'.$version.'_'.md5(json_encode($filters));

        return (int) Cache::remember($key, self::COUNT_CACHE_TTL, function () use ($query) {
            return (int) $query->toBase()->getCountForPagination();
        });
    }
}
