<?php

namespace App\Infrastructure\Services;

use App\Domain\Order\Interfaces\StockServiceInterface;
use App\Models\Book;

class StockService implements StockServiceInterface
{
    public function validateAvailability(array $items): void
    {
        $lines = $this->normalizeLines($items);
        if ($lines === []) {
            return;
        }

        $books = $this->loadBooks(array_keys($lines));

        foreach ($lines as $bookId => $quantity) {
            $book = $books[$bookId] ?? null;
            if (! $book) {
                throw new \InvalidArgumentException("Book not found: {$bookId}");
            }

            $this->assertPurchasable($book, $quantity);
        }
    }

    /**
     * Each line is a single conditional update (stock_quantity >= qty), so concurrent
     * sales cannot oversell even without Mongo transactions. Lines already applied
     * are rolled back if a later line fails.
     */
    public function validateAndDeduct(array $items): void
    {
        $lines = $this->normalizeLines($items);
        if ($lines === []) {
            return;
        }

        $books = $this->loadBooks(array_keys($lines));
        foreach ($lines as $bookId => $quantity) {
            if (! isset($books[$bookId])) {
                throw new \InvalidArgumentException("Book not found: {$bookId}");
            }
            $this->assertPurchasable($books[$bookId], $quantity);
        }

        $applied = [];
        foreach ($lines as $bookId => $quantity) {
            if (! $this->decrementIfAvailable($bookId, $quantity)) {
                foreach ($applied as $appliedId => $appliedQty) {
                    $this->incrementStock($appliedId, $appliedQty);
                }

                $book = $books[$bookId];
                $available = (int) (Book::query()->whereKey($bookId)->value('stock_quantity') ?? 0);
                throw new \InvalidArgumentException(
                    "Insufficient stock for '{$book->title}'. Available: {$available}, requested: {$quantity}"
                );
            }
            $applied[$bookId] = $quantity;
        }

        foreach (array_keys($applied) as $bookId) {
            if ($books[$bookId]->isUsed()) {
                $this->markUsedSoldIfDepleted($bookId);
            }
        }
    }

    public function restore(array $items): void
    {
        foreach ($this->normalizeLines($items) as $bookId => $quantity) {
            if ($this->incrementStock($bookId, $quantity) > 0) {
                Book::query()
                    ->whereKey($bookId)
                    ->where('condition', 'used')
                    ->where('stock_quantity', '>', 0)
                    ->update(['is_sold' => false]);
            }
        }
    }

    /**
     * Atomic conditional decrement. Returns false when stock is insufficient or the
     * book became unavailable concurrently.
     */
    protected function decrementIfAvailable(string $bookId, int $quantity): bool
    {
        $modified = Book::query()
            ->whereKey($bookId)
            ->where('stock_quantity', '>=', $quantity)
            ->where(fn ($q) => $q->where('is_sold', false)->orWhereNull('is_sold'))
            ->where(fn ($q) => $q->where('is_visible', true)->orWhereNull('is_visible'))
            ->decrement('stock_quantity', $quantity);

        return (int) $modified > 0;
    }

    protected function incrementStock(string $bookId, int $quantity): int
    {
        return (int) Book::query()->whereKey($bookId)->increment('stock_quantity', $quantity);
    }

    private function markUsedSoldIfDepleted(string $bookId): void
    {
        Book::query()
            ->whereKey($bookId)
            ->where('stock_quantity', '<=', 0)
            ->update(['stock_quantity' => 0, 'is_sold' => true]);
    }

    /**
     * Merge duplicate lines so a book appearing twice is checked against its combined quantity.
     *
     * @return array<string, int>
     */
    private function normalizeLines(array $items): array
    {
        $lines = [];
        foreach ($items as $item) {
            $bookId = isset($item['book_id']) ? (string) $item['book_id'] : '';
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($bookId === '' || $quantity <= 0) {
                continue;
            }
            $lines[$bookId] = ($lines[$bookId] ?? 0) + $quantity;
        }

        return $lines;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, Book>
     */
    private function loadBooks(array $ids): array
    {
        $byId = [];
        foreach (Book::query()->findMany($ids) as $book) {
            $byId[(string) $book->getKey()] = $book;
        }

        return $byId;
    }

    private function assertPurchasable(Book $book, int $quantity): void
    {
        if (! ($book->is_visible ?? true)) {
            throw new \InvalidArgumentException("Book '{$book->title}' is hidden and cannot be purchased.");
        }

        if ($book->is_sold ?? false) {
            throw new \InvalidArgumentException("Book '{$book->title}' is already sold.");
        }

        if ($book->stock_quantity < $quantity) {
            throw new \InvalidArgumentException(
                "Insufficient stock for '{$book->title}'. Available: {$book->stock_quantity}, requested: {$quantity}"
            );
        }
    }
}
