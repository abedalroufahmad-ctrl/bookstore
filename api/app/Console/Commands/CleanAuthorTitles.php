<?php

namespace App\Console\Commands;

use App\Infrastructure\Services\CachedCatalogService;
use App\Models\Author;
use Illuminate\Console\Command;

class CleanAuthorTitles extends Command
{
    protected $signature = 'authors:clean-titles
                            {--dry-run : Show what would be changed without updating}';

    protected $description = 'Remove academic titles (د.، أ.د.، أ.، الدكتور، Dr.) from author names';

    /** Patterns to remove (order matters: longer/more specific first) */
    private const TITLE_PATTERNS = [
        '/^أ\.\s*د\.\s*،?\s*/u',      // أ. د. (with space)
        '/^أ\.د\.?\s*،?\s*/u',        // أ.د. or أ.د
        '/^الدكتور\s+/u',             // الدكتور
        '/^Dr\.\s+/i',                // Dr.
        '/^د\.\s*،?\s*/u',            // د.
        '/^أ\.\s*،?\s*/u',            // أ.
    ];

    public function handle(CachedCatalogService $catalogService): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry run – no changes will be made.');
        }

        $authors = Author::all();
        $updated = 0;

        foreach ($authors as $author) {
            $original = $author->name;
            $cleaned = $this->cleanName($original);

            if ($cleaned !== $original) {
                $this->line("  {$original} → {$cleaned}");

                if (! $dryRun) {
                    $author->update(['name' => $cleaned]);
                }

                $updated++;
            }
        }

        if (! $dryRun && $updated > 0) {
            $catalogService->forgetCatalogCache();
        }

        $this->info($dryRun
            ? "Would update {$updated} author(s). Run without --dry-run to apply."
            : "Updated {$updated} author(s)."
        );

        return self::SUCCESS;
    }

    private function cleanName(string $name): string
    {
        $cleaned = trim($name);

        foreach (self::TITLE_PATTERNS as $pattern) {
            $cleaned = preg_replace($pattern, '', $cleaned);
        }

        return trim($cleaned);
    }
}
