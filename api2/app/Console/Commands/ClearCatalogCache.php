<?php

namespace App\Console\Commands;

use App\Infrastructure\Services\CachedCatalogService;
use Illuminate\Console\Command;

class ClearCatalogCache extends Command
{
    protected $signature = 'catalog:clear-cache';

    protected $description = 'Invalidate catalog cache (authors, categories, books) so fresh data is fetched';

    public function handle(CachedCatalogService $catalogService): int
    {
        $catalogService->forgetCatalogCache();

        $this->info('Catalog cache invalidated. Fresh data will be fetched on next request.');

        return self::SUCCESS;
    }
}
