<?php

namespace App\Infrastructure\Repositories\Mongo;

use App\Domain\Book\Interfaces\BookRepositoryInterface;
use App\Models\Author;
use App\Models\Book;
use App\Models\Publisher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class BookRepository implements BookRepositoryInterface
{
    private const COUNT_CACHE_TTL = 600;

    public function __construct(
        protected Book $model
    ) {}

    public function findById(string $id, array $with = []): ?Book
    {
        $query = $this->model->newQuery();

        if (! empty($with)) {
            $query->with($with);
        }

        return $query->find($id);
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $with = $filters['with'] ?? ['category', 'warehouse', 'authors', 'publisher'];
        $loadAuthorsManually = in_array('authors', $with, true);
        $eloquentWith = array_values(array_filter($with, fn ($relation) => $relation !== 'authors'));

        $query = $this->model->newQuery();

        if (! empty($eloquentWith)) {
            $query->with($eloquentWith);
        }

        $this->applyFilters($query, $filters);

        $page = max(1, (int) (request()->get('page') ?: 1));
        $total = $this->cachedTotal(clone $query, $filters);

        $items = (clone $query)
            ->orderByDesc('created_at')
            ->orderByDesc('_id')
            ->forPage($page, $perPage)
            ->get();

        if ($loadAuthorsManually) {
            $this->hydrateAuthors($items);
        }

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

    public function create(array $data): Book
    {
        $data['has_cover'] = $this->computeHasCover($data);

        return $this->model->create($data);
    }

    public function update(string $id, array $data): bool
    {
        $model = $this->findById($id);

        if (! $model) {
            return false;
        }

        if (array_key_exists('cover_image', $data) || array_key_exists('cover_image_thumb', $data)) {
            $data['has_cover'] = $this->computeHasCover([
                'cover_image' => $data['cover_image'] ?? $model->cover_image,
                'cover_image_thumb' => $data['cover_image_thumb'] ?? $model->cover_image_thumb,
            ]);
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

    private function applyFilters($query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $publisherIds = Publisher::query()
                    ->where('name', 'like', "%{$search}%")
                    ->limit(50)
                    ->pluck('_id')
                    ->all();

                $query->where(function ($q) use ($search, $publisherIds) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%");

                    if (! empty($publisherIds)) {
                        $q->orWhereIn('publisher_id', $publisherIds);
                    }
                });
            }
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }
        if (! empty($filters['warehouse_ids']) && is_array($filters['warehouse_ids'])) {
            $query->whereIn('warehouse_id', array_values($filters['warehouse_ids']));
        }

        if (! empty($filters['publisher_id'])) {
            $query->where('publisher_id', $filters['publisher_id']);
        }

        if (! empty($filters['author_id'])) {
            $query->where('author_ids', $filters['author_id']);
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', (float) $filters['max_price']);
        }

        if (isset($filters['in_stock'])) {
            if ($filters['in_stock']) {
                $query->where('stock_quantity', '>', 0);
            } else {
                $query->where('stock_quantity', 0);
            }
        }

        if (! empty($filters['has_cover'])) {
            $query->where('has_cover', true);
        }

        if (! empty($filters['no_cover'])) {
            $query->where(function ($q) {
                $q->where('has_cover', false)
                    ->orWhereNull('has_cover');
            });
        }
    }

    private function cachedTotal($query, array $filters): int
    {
        $version = (int) Cache::get('bookstore_catalog_version', 0);
        $fingerprint = $filters;
        unset($fingerprint['with']);

        $key = 'bookstore_books_total_v'.$version.'_'.md5(json_encode($fingerprint));

        return (int) Cache::remember($key, self::COUNT_CACHE_TTL, function () use ($query) {
            return (int) $query->toBase()->getCountForPagination();
        });
    }

    private function computeHasCover(array $data): bool
    {
        $cover = trim((string) ($data['cover_image'] ?? ''));
        $thumb = trim((string) ($data['cover_image_thumb'] ?? ''));

        return $cover !== '' || $thumb !== '';
    }

    /**
     * belongsToMany can miss authors when author_ids are stored as strings.
     * Batch-load with whereIn so catalog cards get author names in one query.
     */
    private function hydrateAuthors(Collection $books): void
    {
        $authorIds = $books
            ->flatMap(fn (Book $book) => $book->author_ids ?? [])
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($authorIds === []) {
            foreach ($books as $book) {
                $book->setRelation('authors', collect());
            }

            return;
        }

        $authorsById = Author::query()
            ->whereIn('_id', $authorIds)
            ->get(['_id', 'name', 'photo'])
            ->keyBy(fn (Author $author) => (string) $author->getKey());

        foreach ($books as $book) {
            $related = collect($book->author_ids ?? [])
                ->map(fn ($id) => $authorsById[(string) $id] ?? null)
                ->filter()
                ->values();
            $book->setRelation('authors', $related);
        }
    }
}

