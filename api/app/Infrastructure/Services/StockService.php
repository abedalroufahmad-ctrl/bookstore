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

            if ($book->stock_quantity < $quantity) {
                throw new \InvalidArgumentException(
                    "Insufficient stock for '{$book->title}'. Available: {$book->stock_quantity}, requested: {$quantity}"
                );
            }
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
            if ($book) {
                $book->decrement('stock_quantity', $quantity);
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
            if ($book) {
                $book->increment('stock_quantity', $quantity);
            }
        }
    }
}
