<?php

namespace App\Infrastructure\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class CachedCatalogService
{
    public function __construct(
        protected CategoryService $categoryService,
        protected AuthorService $authorService,
        protected BookService $bookService
    ) {}

    public function getCachedCategories(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        if (! config('catalog.cache_enabled', true)) {
            return $this->categoryService->getAll($filters, $perPage);
        }

        $key = $this->cacheKey('categories', $filters, $perPage);
        $ttl = config('catalog.cache_ttl.categories', 3600);

        return Cache::remember($key, $ttl, fn () => $this->categoryService->getAll($filters, $perPage));
    }

    public function getCachedAuthors(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        if (! config('catalog.cache_enabled', true)) {
            return $this->authorService->getAll($filters, $perPage);
        }

        $key = $this->cacheKey('authors', $filters, $perPage);
        $ttl = config('catalog.cache_ttl.authors', 3600);

        return Cache::remember($key, $ttl, fn () => $this->authorService->getAll($filters, $perPage));
    }

    public function getCachedBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        if (! config('catalog.cache_enabled', true)) {
            return $this->bookService->getAll($filters, $perPage);
        }

        $key = $this->cacheKey('books', $filters, $perPage);
        $ttl = config('catalog.cache_ttl.books', 300);

        return Cache::remember($key, $ttl, fn () => $this->bookService->getAll($filters, $perPage));
    }

    /**
     * Invalidate catalog cache. Call when admin updates categories, authors, or books.
     * With file driver, we rely on TTL; with Redis, consider cache tags for granular invalidation.
     */
    public function forgetCatalogCache(): void
    {
        // TTL-based expiry is primary; manual flush only when needed
        // Cache::tags(['catalog'])->flush(); // Use with Redis
    }

    private function cacheKey(string $type, array $filters, int $perPage): string
    {
        $prefix = config('catalog.cache_prefix', 'bookstore_catalog_');

        return $prefix . $type . '_' . md5(json_encode($filters) . $perPage);
    }
}
