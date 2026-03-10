<?php

namespace App\Services;

use App\Domain\Cart\Enums\CartStatus;
use App\Domain\Cart\Interfaces\CartRepositoryInterface;
use App\Domain\Cart\Interfaces\CartServiceInterface;
use App\Models\Book;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Support\Collection;

class CartService extends BaseService implements CartServiceInterface
{
    public function __construct(
        protected CartRepositoryInterface $cartRepository
    ) {}

    public function getOrCreateActiveCart(Customer $customer): Cart
    {
        $cart = $this->cartRepository->findActiveByCustomer($customer);

        if (! $cart) {
            $cart = $this->cartRepository->create([
                'customer_id' => $customer->getKey(),
                'items' => [],
                'status' => CartStatus::Active->value,
            ]);
        }

        return $cart;
    }

    public function addBook(Cart $cart, string $bookId, int $quantity = 1): Cart
    {
        $book = Book::find($bookId);
        if (! $book) {
            throw new \InvalidArgumentException('Book not found.');
        }

        if ($book->stock_quantity < $quantity) {
            throw new \InvalidArgumentException("Insufficient stock. Available: {$book->stock_quantity}");
        }

        $items = collect($cart->items ?? []);
        $existingIndex = $items->search(fn ($item) => ($item['book_id'] ?? '') === $bookId);

        if ($existingIndex !== false) {
            $newQty = ($items[$existingIndex]['quantity'] ?? 0) + $quantity;
            if ($book->stock_quantity < $newQty) {
                throw new \InvalidArgumentException("Insufficient stock. Available: {$book->stock_quantity}");
            }
            $items[$existingIndex]['quantity'] = $newQty;
            $items[$existingIndex]['price'] = $this->calculateDiscountedPrice($book);
        } else {
            $items->push([
                'book_id' => $bookId,
                'quantity' => $quantity,
                'price' => $this->calculateDiscountedPrice($book),
            ]);
        }

        $this->cartRepository->update($cart->getKey(), ['items' => $items->values()->all()]);

        return $cart->fresh();
    }

    public function removeBook(Cart $cart, string $bookId): Cart
    {
        $items = collect($cart->items ?? [])
            ->reject(fn ($item) => ($item['book_id'] ?? '') === $bookId)
            ->values()
            ->all();

        $this->cartRepository->update($cart->getKey(), ['items' => $items]);

        return $cart->fresh();
    }

    public function updateQuantity(Cart $cart, string $bookId, int $quantity): Cart
    {
        if ($quantity <= 0) {
            return $this->removeBook($cart, $bookId);
        }

        $book = Book::find($bookId);
        if (! $book) {
            throw new \InvalidArgumentException('Book not found.');
        }

        if ($book->stock_quantity < $quantity) {
            throw new \InvalidArgumentException("Insufficient stock. Available: {$book->stock_quantity}");
        }

        $items = collect($cart->items ?? []);
        $existingIndex = $items->search(fn ($item) => ($item['book_id'] ?? '') === $bookId);

        if ($existingIndex === false) {
            throw new \InvalidArgumentException('Book not in cart.');
        }

        $items[$existingIndex]['quantity'] = $quantity;
        $items[$existingIndex]['price'] = $this->calculateDiscountedPrice($book);

        $this->cartRepository->update($cart->getKey(), ['items' => $items->values()->all()]);

        return $cart->fresh();
    }

    public function calculateTotal(Cart $cart): float
    {
        $items = collect($cart->items ?? []);

        return $items->reduce(function (float $total, array $item) {
            return $total + (($item['price'] ?? 0) * ($item['quantity'] ?? 0));
        }, 0.0);
    }

    public function markAsConverted(Cart $cart): void
    {
        $this->cartRepository->update($cart->getKey(), ['status' => CartStatus::Converted->value]);
    }

    public function getItemsWithDetails(Cart $cart): Collection
    {
        $items = collect($cart->items ?? []);

        return $items->map(function (array $item) {
            $book = Book::find($item['book_id'] ?? null);
            $currentPrice = $book ? $this->calculateDiscountedPrice($book) : ($item['price'] ?? 0);

            return [
                'book_id' => $item['book_id'],
                'quantity' => $item['quantity'],
                'price' => $currentPrice,
                'subtotal' => round($currentPrice * ($item['quantity'] ?? 0), 2),
                'book' => $book ? [
                    'id' => $book->getKey(),
                    'title' => $book->title,
                    'price' => $book->price,
                    'discount_percent' => $book->discount_percent,
                ] : null,
            ];
        });
    }

    protected function calculateDiscountedPrice(Book $book): float
    {
        $discount = $book->discount_percent;
        if (!$discount || $discount <= 0) {
            $discount = Setting::get('global_discount', 0);
        }

        return round($book->price * (1 - $discount / 100), 2);
    }
}
