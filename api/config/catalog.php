<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Catalog Cache Enabled
    |--------------------------------------------------------------------------
    | Enable caching for public catalog endpoints. Uses CACHE_STORE (file/redis).
    */
    // Disabled by default in this environment to avoid filesystem permission issues with file cache.
    'cache_enabled' => filter_var(env('CACHE_CATALOG_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    /*
    |--------------------------------------------------------------------------
    | Catalog Cache TTL (seconds)
    |--------------------------------------------------------------------------
    | How long to cache categories, authors, and public book list.
    | Categories/Authors change rarely; books may change with stock.
    */
    'cache_ttl' => [
        'categories' => (int) env('CACHE_CATEGORIES_TTL', 3600),      // 1 hour
        'authors' => (int) env('CACHE_AUTHORS_TTL', 3600),            // 1 hour
        'books' => (int) env('CACHE_BOOKS_TTL', 900),                 // 15 minutes
        'warehouses' => (int) env('CACHE_WAREHOUSES_TTL', 3600),      // 1 hour
        // Exact filtered totals are expensive at 1M+ docs — cache longer.
        'books_total' => (int) env('CACHE_BOOKS_TOTAL_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Offset pagination safety
    |--------------------------------------------------------------------------
    | Deep OFFSET/SKIP on MongoDB is O(n). Public catalog pages beyond this
    | are clamped; clients should use search/filters instead of jumping to page 50k.
    */
    'max_offset_page' => (int) env('CATALOG_MAX_OFFSET_PAGE', 200),

    /*
    |--------------------------------------------------------------------------
    | Cache Keys Prefix
    |--------------------------------------------------------------------------
    */
    'cache_prefix' => 'bookstore_catalog_',
];
