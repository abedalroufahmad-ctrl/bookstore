<?php

namespace App\Infrastructure\Services;

use App\Domain\Order\Interfaces\StockServiceInterface;
use App\Models\Book;

class StockService implements StockServiceInterface
{
    public function validateAvailability(array $items): void
    {
        foreach ($items as $item) {
            $bookId = $item['book_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);

            if (! $bookId || $quantity <= 0) {
                continue;
            }

            $book = Book::find($bookId);
            if (! $book) {
                throw new \InvalidArgumentException("Book not found: {$bookId}");
            }

            $this->assertPurchasable($book, $quantity);
        }
    }

    public function validateAndDeduct(array $items): void
    {
        $this->validateAvailability($items);

        foreach ($items as $item) {
            $bookId = $item['book_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);

            if (! $bookId || $quantity <= 0) {
                continue;
            }

            $book = Book::find($bookId);
            if (! $book) {
                continue;
            }

            $book->decrement('stock_quantity', $quantity);
            $book->refresh();

            if ($book->isUsed() && (int) $book->stock_quantity <= 0) {
                $book->update([
                    'stock_quantity' => 0,
                    'is_sold' => true,
                ]);
            }
        }
    }

    public function restore(array $items): void
    {
        foreach ($items as $item) {
            $bookId = $item['book_id'] ?? null;
            $quantity = (int) ($item['quantity'] ?? 0);

            if (! $bookId || $quantity <= 0) {
                continue;
            }

            $book = Book::find($bookId);
            if (! $book) {
                continue;
            }

            $book->increment('stock_quantity', $quantity);
            $book->refresh();

            if ($book->isUsed() && (int) $book->stock_quantity > 0) {
                $book->update(['is_sold' => false]);
            }
        }
    }

    private function assertPurchasable(Book $book, int $quantity): void
    {
        if (! ($book->is_visible ?? true)) {
            throw new \InvalidArgumentException("Book '{$book->title}' is hidden and cannot be purchased.");
        }

        if ($book->is_sold ?? false) {
            throw new \InvalidArgumentException("Book '{$book->title}' is already sold.");
        }

        if ($book->isUsed() && $quantity > 1) {
            throw new \InvalidArgumentException("Used book '{$book->title}' can only be purchased once.");
        }

        if ($book->stock_quantity < $quantity) {
            throw new \InvalidArgumentException(
                "Insufficient stock for '{$book->title}'. Available: {$book->stock_quantity}, requested: {$quantity}"
            );
        }
    }
}
