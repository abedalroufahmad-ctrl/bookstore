<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Catalog cache keys embed a version number; bumping it invalidates every cached catalog
 * page at once without Cache::flush() (which would also wipe the JWT blacklist and
 * rate-limiter counters that share the store).
 */
final class CatalogCache
{
    public const VERSION_KEY = 'bookstore_catalog_version';

    public static function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 0);
    }

    public static function bump(): void
    {
        Cache::put(self::VERSION_KEY, self::version() + 1, now()->addYear());
    }
}
