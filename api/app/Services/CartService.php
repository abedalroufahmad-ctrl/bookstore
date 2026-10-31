<?php

namespace App\Services;

use App\Domain\Cart\Enums\CartStatus;
use App\Domain\Cart\Interfaces\CartRepositoryInterface;
use App\Domain\Cart\Interfaces\CartServiceInterface;
use App\Models\Book;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Setting;
use App\Support\CurrencyAmount;
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

    public function addBook(Cart $cart, string $bookId, int $quantity = 1, ?string $currencyCode = null): Cart
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
            $items[$existingIndex]['price'] = $this->calculateDiscountedPrice($book, $currencyCode);
            $items[$existingIndex]['currency_code'] = $currencyCode;
        } else {
            $items[] = [
                'book_id' => $bookId,
                'quantity' => $quantity,
                'price' => $this->calculateDiscountedPrice($book, $currencyCode),
                'currency_code' => $currencyCode,
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

    public function updateQuantity(Cart $cart, string $bookId, int $quantity, ?string $currencyCode = null): Cart
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
        $items[$existingIndex]['price'] = $this->calculateDiscountedPrice($book, $currencyCode);
        $items[$existingIndex]['currency_code'] = $currencyCode;

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
        if ($book->stock_quantity < $quantity) {
            throw new \InvalidArgumentException("Insufficient stock. Available: {$book->stock_quantity}");
        }
    }

    public function calculateTotal(Cart $cart, ?string $currencyCode = null): ?float
    {
        try {
            $items = $this->repriceItems($cart->items ?? [], $currencyCode);
        } catch (\InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'not offered in')) {
                return null;
            }
            throw $e;
        }

        $total = collect($items)->reduce(function (float $total, array $item) {
            return $total + (($item['price'] ?? 0) * ($item['quantity'] ?? 0));
        }, 0.0);

        return CurrencyAmount::round($total, $currencyCode ?? 'USD');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function repriceItems(array $items, ?string $currencyCode = null): array
    {
        $books = $this->loadBooksById($items, ['publisher']);
        $repriced = [];
        foreach ($items as $item) {
            $bookId = $item['book_id'] ?? null;
            if (! $bookId) {
                continue;
            }
            $book = $books[(string) $bookId] ?? null;
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
            if ($book->stock_quantity < $qty) {
                throw new \InvalidArgumentException("Insufficient stock for '{$book->title}'.");
            }
            $repriced[] = [
                'book_id' => (string) $bookId,
                'quantity' => $qty,
                'price' => $this->calculateDiscountedPrice($book, $currencyCode),
                'currency_code' => $currencyCode ?? ($book ? $this->defaultCurrencyCode($book) : (string) ($item['currency_code'] ?? 'USD')),
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

    public function getItemsWithDetails(Cart $cart, ?string $currencyCode = null): Collection
    {
        $items = collect($cart->items ?? []);
        $books = $this->loadBooksById($items->all(), ['warehouse', 'publisher']);

        return $items->map(function (array $item) use ($books, $currencyCode) {
            $book = $books[(string) ($item['book_id'] ?? '')] ?? null;
            $currentPrice = null;
            $currencyAvailable = false;
            if ($book) {
                try {
                    $currentPrice = $this->calculateDiscountedPrice($book, $currencyCode);
                    $currencyAvailable = true;
                } catch (\InvalidArgumentException) {
                    // Keep the cart visible so customers can remove items after changing currency.
                }
            }

            return [
                'book_id' => $item['book_id'],
                'quantity' => $item['quantity'],
                'price' => $currentPrice,
                'subtotal' => $currentPrice === null
                    ? null
                    : CurrencyAmount::round(
                        $currentPrice * ($item['quantity'] ?? 0),
                        $currencyCode ?? $this->defaultCurrencyCode($book)
                    ),
                'currency_code' => $currencyCode ?? $this->defaultCurrencyCode($book),
                'currency_available' => $currencyAvailable,
                'book' => $book ? [
                    'id' => $book->getKey(),
                    'title' => $book->title,
                    'price' => $book->price,
                    'prices' => $book->prices,
                    'discount_percent' => $book->discount_percent,
                    'warehouse' => $book->warehouse ? [
                        'id' => $book->warehouse->getKey(),
                        'name' => $book->warehouse->name,
                    ] : null,
                    'publisher' => $book->publisher ? [
                        'id' => $book->publisher->getKey(),
                        'name' => $book->publisher->name,
                        'settings' => $book->publisher->public_settings,
                    ] : null,
                ] : null,
            ];
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, Book>
     */
    private function loadBooksById(array $items, array $with = []): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (! empty($item['book_id'])) {
                $ids[] = (string) $item['book_id'];
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        $byId = [];
        foreach (Book::query()->with($with)->findMany($ids) as $book) {
            $byId[(string) $book->getKey()] = $book;
        }

        return $byId;
    }

    protected function calculateDiscountedPrice(Book $book, ?string $currencyCode = null): float
    {
        $currencyCode = $currencyCode !== null ? strtoupper($currencyCode) : null;
        $prices = is_array($book->prices) ? $book->prices : [];
        if ($currencyCode !== null) {
            $settings = is_array($book->publisher?->settings) ? $book->publisher->settings : [];
            $enabledCodes = array_column(
                array_filter($settings['currencies'] ?? [], 'is_array'),
                'code'
            );
            if ($enabledCodes === []) {
                $enabledCodes = ['USD'];
            }
            if (! in_array($currencyCode, $enabledCodes, true)) {
                throw new \InvalidArgumentException("This book is not offered in {$currencyCode}.");
            }
            if (array_key_exists($currencyCode, $prices)) {
                $basePrice = (float) $prices[$currencyCode];
            } elseif ($currencyCode === $this->defaultCurrencyCode($book)) {
                $basePrice = (float) $book->price;
            } else {
                throw new \InvalidArgumentException("This book is not offered in {$currencyCode}.");
            }
        } else {
            $basePrice = (float) $book->price;
        }

        $discount = $book->discount_percent;
        if (! $discount || $discount <= 0) {
            $discount = Setting::get('global_discount', 0);
        }

        return CurrencyAmount::round(
            $basePrice * (1 - $discount / 100),
            $currencyCode ?? $this->defaultCurrencyCode($book)
        );
    }

    private function defaultCurrencyCode(Book $book): string
    {
        if (! $book->relationLoaded('publisher')) {
            $book->load('publisher');
        }
        $settings = is_array($book->publisher?->settings) ? $book->publisher->settings : [];

        return strtoupper((string) ($settings['default_currency_code'] ?? 'USD'));
    }
}
