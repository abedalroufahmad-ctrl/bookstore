<?php

namespace Tests\Feature;

use App\Infrastructure\Services\StockService;
use App\Models\Book;

class StockConcurrencyTest extends MongoFeatureTestCase
{
    private function makeBook(int $stock, array $attributes = []): Book
    {
        return Book::create(array_merge([
            'title' => 'Race Book '.uniqid(),
            'price' => 10,
            'stock_quantity' => $stock,
            'condition' => 'new',
            'is_visible' => true,
            'is_sold' => false,
        ], $attributes));
    }

    public function test_competing_sale_between_check_and_deduct_cannot_oversell(): void
    {
        $book = $this->makeBook(1);

        // Simulates another request selling the last copy after our availability check passed.
        $service = new class extends StockService
        {
            protected function decrementIfAvailable(string $bookId, int $quantity): bool
            {
                Book::query()->whereKey($bookId)->decrement('stock_quantity', 1);

                return parent::decrementIfAvailable($bookId, $quantity);
            }
        };

        try {
            $service->validateAndDeduct([['book_id' => (string) $book->_id, 'quantity' => 1]]);
            $this->fail('Expected insufficient stock exception.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame(0, (int) $book->fresh()->stock_quantity, 'Stock must never go negative.');
    }

    public function test_failed_line_rolls_back_previously_deducted_lines(): void
    {
        $first = $this->makeBook(5);
        $second = $this->makeBook(1);

        $service = new class extends StockService
        {
            public ?string $sabotageId = null;

            protected function decrementIfAvailable(string $bookId, int $quantity): bool
            {
                if ($bookId === $this->sabotageId) {
                    Book::query()->whereKey($bookId)->update(['stock_quantity' => 0]);
                }

                return parent::decrementIfAvailable($bookId, $quantity);
            }
        };
        $service->sabotageId = (string) $second->_id;

        try {
            $service->validateAndDeduct([
                ['book_id' => (string) $first->_id, 'quantity' => 2],
                ['book_id' => (string) $second->_id, 'quantity' => 1],
            ]);
            $this->fail('Expected insufficient stock exception.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(5, (int) $first->fresh()->stock_quantity, 'First line must be rolled back.');
        $this->assertSame(0, (int) $second->fresh()->stock_quantity);
    }

    public function test_duplicate_lines_are_checked_against_combined_quantity(): void
    {
        $book = $this->makeBook(3);
        $id = (string) $book->_id;

        $this->expectException(\InvalidArgumentException::class);
        try {
            (new StockService)->validateAndDeduct([
                ['book_id' => $id, 'quantity' => 2],
                ['book_id' => $id, 'quantity' => 2],
            ]);
        } finally {
            $this->assertSame(3, (int) $book->fresh()->stock_quantity);
        }
    }

    public function test_used_book_is_marked_sold_when_depleted_and_unmarked_on_restore(): void
    {
        $book = $this->makeBook(2, ['condition' => 'used']);
        $line = [['book_id' => (string) $book->_id, 'quantity' => 2]];

        (new StockService)->validateAndDeduct($line);
        $fresh = $book->fresh();
        $this->assertSame(0, (int) $fresh->stock_quantity);
        $this->assertTrue((bool) $fresh->is_sold);

        (new StockService)->restore($line);
        $fresh = $book->fresh();
        $this->assertSame(2, (int) $fresh->stock_quantity);
        $this->assertFalse((bool) $fresh->is_sold);
    }

    public function test_parallel_processes_never_sell_more_than_available(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open is not available.');
        }

        $stock = 3;
        $workers = 8;
        $book = $this->makeBook($stock);
        $script = __DIR__.'/fixtures/deduct_stock.php';
        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'MONGODB_DATABASE' => (string) config('database.connections.mongodb.database'),
            'CACHE_STORE' => 'array',
        ]);

        $processes = [];
        for ($i = 0; $i < $workers; $i++) {
            $pipes = [];
            $proc = proc_open(
                [PHP_BINARY, $script, (string) $book->_id, '1'],
                [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                $env
            );
            $this->assertIsResource($proc);
            $processes[] = [$proc, $pipes];
        }

        $results = [];
        foreach ($processes as [$proc, $pipes]) {
            $results[] = trim((string) stream_get_contents($pipes[1]));
            $stderr = trim((string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($proc), "Worker failed: {$stderr}");
        }

        $this->assertSame($stock, count(array_filter($results, fn ($r) => $r === 'OK')), 'Exactly the available copies should sell.');
        $this->assertSame($workers - $stock, count(array_filter($results, fn ($r) => $r === 'FAIL')));
        $this->assertSame(0, (int) $book->fresh()->stock_quantity);
    }
}
