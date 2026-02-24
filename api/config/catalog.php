<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Catalog Cache Enabled
    |--------------------------------------------------------------------------
    | Enable caching for public catalog endpoints. Uses CACHE_STORE (file/redis).
    */
    'cache_enabled' => filter_var(env('CACHE_CATALOG_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

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
        'books' => (int) env('CACHE_BOOKS_TTL', 300),                 // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Keys Prefix
    |--------------------------------------------------------------------------
    */
    'cache_prefix' => 'bookstore_catalog_',
];
