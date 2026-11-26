<?php

namespace Tests\Feature;

use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Models\Book;
use App\Models\Customer;
use App\Models\Publisher;
use App\Models\Warehouse;
use App\Services\CartService;

class CartWarehouseCurrencyTest extends MongoFeatureTestCase
{
    public function test_cart_shows_the_warehouse_publisher_currency_when_the_shopper_currency_is_not_offered(): void
    {
        $publisher = Publisher::create([
            'name' => 'Local Publisher',
            'settings' => [
                'default_currency_code' => 'SYP',
                'currency_symbol' => 'ل.س',
                'currencies' => [
                    ['code' => 'SYP', 'name' => 'Syrian Pound', 'symbol' => 'ل.س'],
                ],
            ],
        ]);
        $warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'publisher_id' => (string) $publisher->_id,
        ]);
        $book = Book::create([
            'title' => 'Local Title',
            'price' => 1500,
            'discount_percent' => 10,
            'stock_quantity' => 4,
            'condition' => 'new',
            'is_visible' => true,
            'is_sold' => false,
            'warehouse_id' => (string) $warehouse->_id,
            'publisher_id' => (string) $publisher->_id,
        ]);

        $customer = Customer::create([
            'name' => 'Shopper',
            'email' => 'shopper-currency@example.test',
            'password' => 'secret123',
        ]);
        $cartService = app(CartService::class);
        $cart = $cartService->addBook(
            $cartService->getOrCreateActiveCart($customer),
            (string) $book->_id,
            2,
            'SYP'
        );

        $items = $cartService->getItemsWithDetails($cart, 'USD');

        $this->assertCount(1, $items);
        $line = $items->first();
        $this->assertFalse($line['currency_available']);
        $this->assertSame('SYP', $line['currency_code']);
        $this->assertSame('ل.س', $line['currency_symbol']);
        $this->assertSame(1350.0, $line['price']);
        $this->assertSame(2700.0, $line['subtotal']);
        $this->assertSame('SYP', $line['book']['warehouse']['currency_code']);
        $this->assertNull($cartService->calculateTotal($cart, 'USD'));
    }

    public function test_checkout_uses_each_warehouses_currency_when_the_shopper_currency_is_not_offered(): void
    {
        $localPublisher = Publisher::create([
            'name' => 'Local Publisher',
            'settings' => [
                'default_currency_code' => 'SYP',
                'currency_symbol' => 'ل.س',
                'currencies' => [
                    ['code' => 'SYP', 'name' => 'Syrian Pound', 'symbol' => 'ل.س'],
                ],
            ],
        ]);
        $usdPublisher = Publisher::create([
            'name' => 'USD Publisher',
            'settings' => [
                'default_currency_code' => 'USD',
                'currency_symbol' => '$',
                'currencies' => [
                    ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
                ],
            ],
        ]);
        $localWarehouse = Warehouse::create([
            'name' => 'Local Warehouse',
            'publisher_id' => (string) $localPublisher->_id,
        ]);
        $usdWarehouse = Warehouse::create([
            'name' => 'USD Warehouse',
            'publisher_id' => (string) $usdPublisher->_id,
        ]);
        $localBook = Book::create([
            'title' => 'Local Title',
            'price' => 100,
            'discount_percent' => 10,
            'stock_quantity' => 3,
            'condition' => 'new',
            'is_visible' => true,
            'is_sold' => false,
            'warehouse_id' => (string) $localWarehouse->_id,
            'publisher_id' => (string) $localPublisher->_id,
        ]);
        $usdBook = Book::create([
            'title' => 'USD Title',
            'price' => 20,
            'discount_percent' => 10,
            'stock_quantity' => 3,
            'condition' => 'new',
            'is_visible' => true,
            'is_sold' => false,
            'warehouse_id' => (string) $usdWarehouse->_id,
            'publisher_id' => (string) $usdPublisher->_id,
        ]);

        $customer = Customer::create([
            'name' => 'Shopper',
            'email' => 'mixed-checkout@example.test',
            'password' => 'secret123',
        ]);
        $cartService = app(CartService::class);
        $cart = $cartService->getOrCreateActiveCart($customer);
        $cart = $cartService->addBook($cart, (string) $localBook->_id, 1, 'SYP');
        $cartService->addBook($cart, (string) $usdBook->_id, 1, 'USD');

        $orders = app(OrderServiceInterface::class)->checkout(
            $customer,
            ['address' => 'Street 1', 'city' => 'X'],
            'cod',
            null,
            'USD'
        );

        $currencies = collect($orders)->map(fn ($order) => (string) $order->currency_code)->sort()->values()->all();
        $this->assertSame(['SYP', 'USD'], $currencies);
        $this->assertSame(2, (int) $localBook->fresh()->stock_quantity);
        $this->assertSame(2, (int) $usdBook->fresh()->stock_quantity);
    }
}
