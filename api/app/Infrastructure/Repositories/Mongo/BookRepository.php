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
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class BookRepository implements BookRepositoryInterface
{
    /** Columns needed for catalog cards / admin lists. */
    private const LIST_COLUMNS = [
        '_id',
        'title',
        'price',
        'stock_quantity',
        'isbn',
        'author_ids',
        'category_id',
        'warehouse_id',
        'publisher_id',
        'cover_image',
        'cover_image_thumb',
        'has_cover',
        'discount_percent',
        'publish_year',
        'pages',
        'condition',
        'is_visible',
        'is_sold',
        'created_at',
        'updated_at',
    ];

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

        $query = $this->model->newQuery()->select(self::LIST_COLUMNS);

        if (! empty($eloquentWith)) {
            $query->with($eloquentWith);
        }

        $this->applyFilters($query, $filters);

        $perPage = max(1, min(100, $perPage));
        $requestedPage = max(1, (int) (request()->get('page') ?: 1));
        $maxPage = (int) ($filters['max_page'] ?? 0);
        $page = $maxPage > 0 ? min($requestedPage, $maxPage) : $requestedPage;

        $total = $this->cachedTotal(clone $query, $filters);

        $cursor = request()->get('cursor');
        if (is_string($cursor) && $cursor !== '') {
            $items = $this->fetchByCursor(clone $query, $cursor, $perPage);
            // Cursor pages are sequential; report page as requested (clamped).
            $page = $maxPage > 0 ? min($requestedPage, $maxPage) : max(1, $requestedPage);
        } else {
            $lastPageByTotal = max(1, (int) ceil($total / $perPage));
            if ($maxPage > 0) {
                $lastPageByTotal = min($lastPageByTotal, $maxPage);
            }
            $page = min($page, $lastPageByTotal);
            $items = (clone $query)
                ->orderByDesc('created_at')
                ->orderByDesc('_id')
                ->forPage($page, $perPage)
                ->get();
        }

        if ($loadAuthorsManually) {
            $this->hydrateAuthors($items);
        }

        $paginator = new Paginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );

        $paginator->appends(request()->query());

        if ($items->isNotEmpty() && $items->count() >= $perPage) {
            request()->attributes->set('books_next_cursor', $this->encodeCursor($items->last()));
        }

        return $paginator;
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

    /**
     * Build a cursor token from a book (for next-page seeks).
     */
    public function encodeCursor(Book $book): string
    {
        $created = $book->created_at;
        $ms = $created instanceof \DateTimeInterface
            ? (int) round((float) $created->format('U.u') * 1000)
            : (int) (microtime(true) * 1000);

        return rtrim(strtr(base64_encode((string) json_encode([
            'c' => $ms,
            'i' => (string) $book->getKey(),
        ])), '+/', '-_'), '=');
    }

    private function fetchByCursor($query, string $cursor, int $perPage): Collection
    {
        $decoded = $this->decodeCursor($cursor);
        if (! $decoded) {
            return $query->orderByDesc('created_at')->orderByDesc('_id')->limit($perPage)->get();
        }

        $createdAtValue = $decoded['created_at'];
        $objectId = $decoded['_id'];

        return $query
            ->where(function ($q) use ($createdAtValue, $objectId) {
                $q->where('created_at', '<', $createdAtValue)
                    ->orWhere(function ($q2) use ($createdAtValue, $objectId) {
                        $q2->where('created_at', '=', $createdAtValue)
                            ->where('_id', '<', $objectId);
                    });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('_id')
            ->limit($perPage)
            ->get();
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

        if (! empty($filters['condition'])) {
            $query->where('condition', $filters['condition']);
        }

        if (array_key_exists('is_visible', $filters)) {
            if ($filters['is_visible']) {
                $query->where(function ($q) {
                    $q->where('is_visible', true)->orWhereNull('is_visible');
                });
            } else {
                $query->where('is_visible', false);
            }
        }

        if (array_key_exists('is_sold', $filters)) {
            if ($filters['is_sold']) {
                $query->where('is_sold', true);
            } else {
                $query->where(function ($q) {
                    $q->where('is_sold', false)->orWhereNull('is_sold');
                });
            }
        }
    }

    private function cachedTotal($query, array $filters): int
    {
        $version = (int) Cache::get('bookstore_catalog_version', 0);
        $fingerprint = $filters;
        unset($fingerprint['with'], $fingerprint['max_page']);

        $ttl = (int) config('catalog.cache_ttl.books_total', 3600);
        $key = 'bookstore_books_total_v'.$version.'_'.md5((string) json_encode($fingerprint));

        return (int) Cache::remember($key, $ttl, function () use ($query, $filters) {
            if ($this->canUseCatalogCountHint($filters)) {
                try {
                    return (int) DB::connection('mongodb')
                        ->getCollection('books')
                        ->countDocuments(
                            [
                                'has_cover' => true,
                                'stock_quantity' => ['$gt' => 0],
                                '$and' => [
                                    ['$or' => [['is_visible' => true], ['is_visible' => null]]],
                                    ['$or' => [['is_sold' => false], ['is_sold' => null]]],
                                ],
                            ],
                            ['hint' => 'books_catalog_idx']
                        );
                } catch (\Throwable) {
                    // Fall through if hint unavailable.
                }
            }

            return (int) $query->toBase()->getCountForPagination();
        });
    }

    private function canUseCatalogCountHint(array $filters): bool
    {
        return ! empty($filters['has_cover'])
            && isset($filters['in_stock'])
            && $filters['in_stock'] === true
            && array_key_exists('is_visible', $filters)
            && $filters['is_visible'] === true
            && array_key_exists('is_sold', $filters)
            && $filters['is_sold'] === false
            && empty($filters['search'])
            && empty($filters['category_id'])
            && empty($filters['warehouse_id'])
            && empty($filters['warehouse_ids'])
            && empty($filters['publisher_id'])
            && empty($filters['author_id'])
            && empty($filters['condition'])
            && ! isset($filters['min_price'])
            && ! isset($filters['max_price']);
    }

    private function computeHasCover(array $data): bool
    {
        $cover = trim((string) ($data['cover_image'] ?? ''));
        $thumb = trim((string) ($data['cover_image_thumb'] ?? ''));

        return $cover !== '' || $thumb !== '';
    }

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

    private function decodeCursor(string $cursor): ?array
    {
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }
        $data = json_decode($json, true);
        if (! is_array($data) || empty($data['i']) || empty($data['c'])) {
            return null;
        }

        return [
            'created_at' => new UTCDateTime((int) $data['c']),
            '_id' => new ObjectId((string) $data['i']),
        ];
    }
}
