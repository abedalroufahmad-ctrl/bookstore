<?php

namespace App\Domain\Cart\Interfaces;

use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Support\Collection;

interface CartServiceInterface
{
    public function getOrCreateActiveCart(Customer $customer): Cart;

    public function addBook(Cart $cart, string $bookId, int $quantity = 1, ?string $currencyCode = null): Cart;

    public function removeBook(Cart $cart, string $bookId): Cart;

    public function updateQuantity(Cart $cart, string $bookId, int $quantity, ?string $currencyCode = null): Cart;

    public function calculateTotal(Cart $cart, ?string $currencyCode = null): ?float;

    /**
     * Rebuild cart lines with live discounted book prices (for totals / checkout).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function repriceItems(array $items, ?string $currencyCode = null): array;

    public function getItemsWithDetails(Cart $cart, ?string $currencyCode = null): Collection;

    public function markAsConverted(Cart $cart): void;
}
