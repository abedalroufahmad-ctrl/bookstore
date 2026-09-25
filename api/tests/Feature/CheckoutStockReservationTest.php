<?php

namespace Tests\Feature;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Models\Book;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Publisher;
use App\Models\Warehouse;
use App\Services\CartService;

class CheckoutStockReservationTest extends MongoFeatureTestCase
{
    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $publisher = Publisher::create(['name' => 'Test Publisher']);
        $warehouse = Warehouse::create(['name' => 'Main', 'publisher_id' => (string) $publisher->_id]);
        $this->book = Book::create([
            'title' => 'Last Copy',
            'price' => 20,
            'stock_quantity' => 1,
            'condition' => 'new',
            'is_visible' => true,
            'is_sold' => false,
            'warehouse_id' => (string) $warehouse->_id,
            'publisher_id' => (string) $publisher->_id,
        ]);
    }

    private function customerWithBookInCart(string $email): Customer
    {
        $customer = Customer::create(['name' => 'C', 'email' => $email, 'password' => 'secret123']);
        $cartService = app(CartService::class);
        $cartService->addBook($cartService->getOrCreateActiveCart($customer), (string) $this->book->_id, 1);

        return $customer;
    }

    private function checkout(Customer $customer): array
    {
        return app(OrderServiceInterface::class)->checkout($customer, ['address' => 'Street 1', 'city' => 'X'], 'cod');
    }

    public function test_checkout_reserves_stock_so_a_second_buyer_cannot_order_the_same_copy(): void
    {
        $alice = $this->customerWithBookInCart('alice@example.test');
        $bob = $this->customerWithBookInCart('bob@example.test');

        $orders = $this->checkout($alice);

        $this->assertCount(1, $orders);
        $this->assertTrue((bool) $orders[0]->stock_reserved);
        $this->assertSame(0, (int) $this->book->fresh()->stock_quantity);

        try {
            $this->checkout($bob);
            $this->fail('Second checkout should fail: the only copy is reserved.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame(1, Order::query()->count(), 'No order should be created for the failed checkout.');
        $this->assertSame(0, (int) $this->book->fresh()->stock_quantity);
    }

    public function test_cancel_restores_reserved_stock_exactly_once(): void
    {
        $orders = $this->checkout($this->customerWithBookInCart('carol@example.test'));
        $orderId = (string) $orders[0]->_id;

        // Two stale copies simulate two cancel requests racing each other.
        $first = Order::find($orderId);
        $second = Order::find($orderId);

        $service = app(OrderServiceInterface::class);
        $service->updateStatus($first, OrderStatus::Cancelled->value);
        $service->updateStatus($second, OrderStatus::Cancelled->value);

        $this->assertSame(1, (int) $this->book->fresh()->stock_quantity, 'Stock must be restored once, not twice.');
        $this->assertFalse((bool) Order::find($orderId)->stock_reserved);
    }

    public function test_fulfillment_does_not_deduct_already_reserved_stock_again(): void
    {
        $this->book->update(['stock_quantity' => 2]);
        $orders = $this->checkout($this->customerWithBookInCart('dave@example.test'));
        $order = Order::find((string) $orders[0]->_id);
        $order->update(['status' => OrderStatus::ResubmittedToWarehouse->value]);

        app(OrderServiceInterface::class)->updateStatus($order->fresh(), OrderStatus::ProcessingFulfillment->value);

        $this->assertSame(1, (int) $this->book->fresh()->stock_quantity);
    }

    public function test_legacy_unreserved_order_still_deducts_on_fulfillment(): void
    {
        $this->book->update(['stock_quantity' => 3]);
        $order = Order::create([
            'customer_id' => 'legacy',
            'warehouse_id' => (string) $this->book->warehouse_id,
            'items' => [['book_id' => (string) $this->book->_id, 'quantity' => 1, 'price' => 20]],
            'status' => OrderStatus::ResubmittedToWarehouse->value,
            'total' => 20,
        ]);

        app(OrderServiceInterface::class)->updateStatus($order, OrderStatus::ProcessingFulfillment->value);

        $this->assertSame(2, (int) $this->book->fresh()->stock_quantity);
        $this->assertTrue((bool) $order->fresh()->stock_reserved);
    }
}
