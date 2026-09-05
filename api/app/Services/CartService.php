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

        $this->assertBookPurchasable($book, $quantity);

        $items = array_values($cart->items ?? []);
        $existingIndex = null;
        foreach ($items as $i => $item) {
            if (($item['book_id'] ?? '') === $bookId) {
                $existingIndex = $i;
                break;
            }
        }

        if ($existingIndex !== null) {
            $newQty = ($items[$existingIndex]['quantity'] ?? 0) + $quantity;
            $this->assertBookPurchasable($book, $newQty);
            $items[$existingIndex]['quantity'] = $newQty;
            $items[$existingIndex]['price'] = $this->calculateDiscountedPrice($book);
        } else {
            $items[] = [
                'book_id' => $bookId,
                'quantity' => $quantity,
                'price' => $this->calculateDiscountedPrice($book),
            ];
        }

        $this->cartRepository->update($cart->getKey(), ['items' => $items]);

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

        $this->assertBookPurchasable($book, $quantity);

        $items = array_values($cart->items ?? []);
        $existingIndex = null;
        foreach ($items as $i => $item) {
            if (($item['book_id'] ?? '') === $bookId) {
                $existingIndex = $i;
                break;
            }
        }

        if ($existingIndex === null) {
            throw new \InvalidArgumentException('Book not in cart.');
        }

        $items[$existingIndex]['quantity'] = $quantity;
        $items[$existingIndex]['price'] = $this->calculateDiscountedPrice($book);

        $this->cartRepository->update($cart->getKey(), ['items' => $items]);

        return $cart->fresh();
    }

    private function assertBookPurchasable(Book $book, int $quantity): void
    {
        if (! ($book->is_visible ?? true)) {
            throw new \InvalidArgumentException('This book is not available for purchase.');
        }
        if ($book->is_sold ?? false) {
            throw new \InvalidArgumentException('This book has already been sold.');
        }
        if ($book->isUsed() && $quantity > 1) {
            throw new \InvalidArgumentException('Used books can only be purchased as a single copy.');
        }
        if ($book->stock_quantity < $quantity) {
            throw new \InvalidArgumentException("Insufficient stock. Available: {$book->stock_quantity}");
        }
    }

    public function calculateTotal(Cart $cart): float
    {
        $items = $this->repriceItems($cart->items ?? []);

        return collect($items)->reduce(function (float $total, array $item) {
            return $total + (($item['price'] ?? 0) * ($item['quantity'] ?? 0));
        }, 0.0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function repriceItems(array $items): array
    {
        $repriced = [];
        foreach ($items as $item) {
            $bookId = $item['book_id'] ?? null;
            if (! $bookId) {
                continue;
            }
            $book = Book::find($bookId);
            if (! $book) {
                throw new \InvalidArgumentException("Book not found: {$bookId}");
            }
            if (! $book->isPurchasable()) {
                throw new \InvalidArgumentException("Book '{$book->title}' is not available for purchase.");
            }
            $qty = max(0, (int) ($item['quantity'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            if ($book->isUsed() && $qty > 1) {
                throw new \InvalidArgumentException("Used book '{$book->title}' can only be purchased once.");
            }
            if ($book->stock_quantity < $qty) {
                throw new \InvalidArgumentException("Insufficient stock for '{$book->title}'.");
            }
            $repriced[] = [
                'book_id' => (string) $bookId,
                'quantity' => $qty,
                'price' => $this->calculateDiscountedPrice($book),
                'book_title' => $book->title,
                'weight' => $book->weight !== null && is_numeric($book->weight)
                    ? round((float) $book->weight, 3)
                    : null,
            ];
        }

        return $repriced;
    }

    public function markAsConverted(Cart $cart): void
    {
        $this->cartRepository->update($cart->getKey(), ['status' => CartStatus::Converted->value]);
    }

    public function getItemsWithDetails(Cart $cart): Collection
    {
        $items = collect($cart->items ?? []);

        return $items->map(function (array $item) {
            $book = Book::with(['warehouse', 'publisher'])->find($item['book_id'] ?? null);
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
                    'warehouse' => $book->warehouse ? [
                        'id' => $book->warehouse->getKey(),
                        'name' => $book->warehouse->name,
                    ] : null,
                    'publisher' => $book->publisher ? [
                        'id' => $book->publisher->getKey(),
                        'name' => $book->publisher->name,
                        'settings' => $book->publisher->settings,
                    ] : null,
                ] : null,
            ];
        });
    }

    protected function calculateDiscountedPrice(Book $book): float
    {
        $discount = $book->discount_percent;
        if (! $discount || $discount <= 0) {
            $discount = Setting::get('global_discount', 0);
        }

        return round($book->price * (1 - $discount / 100), 2);
    }
}
