<?php

namespace App\Models\Concerns;

use App\Support\CatalogCache;

/** Public book listings embed these records, so any write must invalidate the catalog cache. */
trait BustsCatalogCache
{
    public static function bootBustsCatalogCache(): void
    {
        static::saved(fn () => CatalogCache::bump());
        static::deleted(fn () => CatalogCache::bump());
    }
}
