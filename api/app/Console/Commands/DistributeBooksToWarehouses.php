<?php

namespace App\Console\Commands;

use App\Models\Book;
use App\Models\Warehouse;
use Illuminate\Console\Command;

class DistributeBooksToWarehouses extends Command
{
    protected $signature = 'books:distribute-warehouses
                            {--chunk=180 : Assign this many consecutive books to one warehouse before advancing}
                            {--dry-run : Show planned distribution without saving}';

    protected $description = 'Assign books to warehouses in fixed-size sequential batches (cycles through warehouses).';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));

        $warehouseIds = Warehouse::orderBy('_id')->pluck('_id')->values();
        if ($warehouseIds->isEmpty()) {
            $this->error('No warehouses found. Create warehouses first.');

            return 1;
        }

        $totalBooks = Book::count();
        if ($totalBooks === 0) {
            $this->warn('No books to assign.');

            return 0;
        }

        $warehouses = Warehouse::orderBy('_id')->get(['_id', 'name']);
        $byId = $warehouses->keyBy(fn ($w) => (string) $w->_id);

        $dryRun = (bool) $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be saved.');
        }

        $counts = array_fill_keys($warehouseIds->map(fn ($id) => (string) $id)->all(), 0);
        $bar = $this->output->createProgressBar($totalBooks);
        $bar->start();

        $index = 0;
        Book::orderBy('_id')->chunk(200, function ($books) use ($warehouseIds, $chunkSize, $dryRun, &$counts, &$index, $bar) {
            $warehouseCount = $warehouseIds->count();
            foreach ($books as $book) {
                $chunkIndex = intdiv($index, $chunkSize);
                $warehouseIndex = $chunkIndex % $warehouseCount;
                $warehouseId = $warehouseIds[$warehouseIndex];
                $counts[(string) $warehouseId]++;

                if (! $dryRun) {
                    $book->warehouse_id = $warehouseId;
                    $book->save();
                }

                $index++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Books processed: {$index} (chunk size: {$chunkSize})");

        foreach ($warehouseIds as $warehouseId) {
            $key = (string) $warehouseId;
            $name = $byId[$key]->name ?? $key;
            $this->line("  {$name}: {$counts[$key]}");
        }

        return 0;
    }
}
