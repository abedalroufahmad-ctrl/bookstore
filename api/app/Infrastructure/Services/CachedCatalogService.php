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
        $page = (int) (request()->get('page') ?: 1);
        if (! config('catalog.cache_enabled', true)) {
            return $this->categoryService->getAll($filters, $perPage);
        }

        $key = $this->cacheKey('categories', $filters, $perPage, $page);
        $ttl = config('catalog.cache_ttl.categories', 3600);

        return Cache::remember($key, $ttl, fn () => $this->categoryService->getAll($filters, $perPage));
    }

    public function getCachedAuthors(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $page = (int) (request()->get('page') ?: 1);
        if (! config('catalog.cache_enabled', true)) {
            return $this->authorService->getAll($filters, $perPage);
        }

        $key = $this->cacheKey('authors', $filters, $perPage, $page);
        $ttl = config('catalog.cache_ttl.authors', 3600);

        return Cache::remember($key, $ttl, fn () => $this->authorService->getAll($filters, $perPage));
    }

    public function getCachedBooks(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $page = (int) (request()->get('page') ?: 1);
        if (! config('catalog.cache_enabled', true)) {
            return $this->bookService->getAll($filters, $perPage);
        }

        $key = $this->cacheKey('books', $filters, $perPage, $page);
        $ttl = config('catalog.cache_ttl.books', 300);

        return Cache::remember($key, $ttl, fn () => $this->bookService->getAll($filters, $perPage));
    }

    /**
     * Invalidate catalog cache. Call when admin updates categories, authors, or books.
     * Bumps a version so all cached catalog data is refetched on next request.
     */
    public function forgetCatalogCache(): void
    {
        $version = (int) Cache::get('bookstore_catalog_version', 0);

        Cache::put('bookstore_catalog_version', $version + 1, now()->addYears(1));
    }

    private function cacheKey(string $type, array $filters, int $perPage, int $page = 1): string
    {
        $prefix = config('catalog.cache_prefix', 'bookstore_catalog_');
        $version = (int) Cache::get('bookstore_catalog_version', 0);

        return $prefix . 'v' . $version . '_' . $type . '_' . md5(json_encode($filters) . $perPage . $page);
    }
}
