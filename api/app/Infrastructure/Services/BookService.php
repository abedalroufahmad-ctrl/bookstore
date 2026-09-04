<?php

namespace App\Infrastructure\Services;

use App\Domain\Book\Interfaces\BookRepositoryInterface;
use App\Models\Book;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class BookService
{
    public function __construct(
        protected BookRepositoryInterface $repository
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $filters['with'] = $filters['with'] ?? ['category', 'warehouse', 'authors', 'publisher'];

        return $this->repository->getPaginated($filters, $perPage);
    }

    public function encodeListCursor(Book $book): string
    {
        return $this->repository->encodeCursor($book);
    }

    public function getById(string $id, array $with = ['category', 'warehouse', 'authors', 'publisher']): ?Book
    {
        return $this->repository->findById($id, $with);
    }

    public function create(array $data): Book
    {
        $data['condition'] = $data['condition'] ?? 'new';
        $data['is_visible'] = array_key_exists('is_visible', $data) ? (bool) $data['is_visible'] : true;
        $data['is_sold'] = array_key_exists('is_sold', $data) ? (bool) $data['is_sold'] : false;
        if (($data['condition'] ?? 'new') === 'used') {
            $data['stock_quantity'] = min(1, max(0, (int) ($data['stock_quantity'] ?? 1)));
        }

        $book = $this->repository->create($data);
        $this->bustCatalogCache();

        return $book;
    }

    public function update(string $id, array $data): ?Book
    {
        $updated = $this->repository->update($id, $data);

        if (! $updated) {
            return null;
        }

        $this->bustCatalogCache();

        return $this->repository->findById($id, ['category', 'warehouse', 'authors', 'publisher']);
    }

    public function delete(string $id): bool
    {
        $deleted = $this->repository->delete($id);

        if ($deleted) {
            $this->bustCatalogCache();
        }

        return $deleted;
    }

    /**
     * Delete many books and bust the catalog cache once.
     *
     * @param  list<string>  $ids
     */
    public function deleteMany(array $ids): int
    {
        $deleted = 0;
        foreach ($ids as $id) {
            if ($this->repository->delete((string) $id)) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->bustCatalogCache();
        }

        return $deleted;
    }

    private function bustCatalogCache(): void
    {
        $version = (int) Cache::get('bookstore_catalog_version', 0);
        Cache::put('bookstore_catalog_version', $version + 1, now()->addYears(1));
    }
}
