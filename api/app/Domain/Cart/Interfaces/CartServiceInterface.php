<?php

namespace App\Domain\Cart\Interfaces;

use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Support\Collection;

interface CartServiceInterface
{
    public function getOrCreateActiveCart(Customer $customer): Cart;

    public function addBook(Cart $cart, string $bookId, int $quantity = 1): Cart;

    public function removeBook(Cart $cart, string $bookId): Cart;

    public function updateQuantity(Cart $cart, string $bookId, int $quantity): Cart;

    public function calculateTotal(Cart $cart): float;

    public function getItemsWithDetails(Cart $cart): Collection;

    public function markAsConverted(Cart $cart): void;
}
