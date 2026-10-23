<?php

namespace Tests\Unit\Services;

use App\Models\Order;
use App\Services\StripePaymentService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StripePaymentServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.stripe.secret' => 'sk_test_example',
            'services.stripe.currency' => 'USD',
            'services.stripe.checkout_success_url' => 'http://localhost:5173/orders/{ORDER_ID}?stripe=success&session_id={CHECKOUT_SESSION_ID}',
            'services.stripe.checkout_cancel_url' => 'http://localhost:5173/orders/{ORDER_ID}?stripe=cancelled',
        ]);
    }

    public function test_creates_card_checkout_for_the_quoted_total(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            ]),
        ]);

        $order = new Order;
        $order->setAttribute('_id', 'order-123');
        $order->total = 27.45;

        $session = app(StripePaymentService::class)->createCheckoutSession($order, now()->addMinutes(31)->timestamp);

        $this->assertSame('cs_test_123', $session['id']);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_123', $session['url']);
        Http::assertSent(function (Request $request): bool {
            $data = $request->data();

            return $request->url() === 'https://api.stripe.com/v1/checkout/sessions'
                && $request->hasHeader('Idempotency-Key')
                && ($data['mode'] ?? null) === 'payment'
                && ($data['line_items'][0]['price_data']['unit_amount'] ?? null) === 2745
                && ($data['payment_intent_data']['metadata']['order_id'] ?? null) === 'order-123'
                && ($data['success_url'] ?? null) === 'http://localhost:5173/orders/order-123?stripe=success&session_id={CHECKOUT_SESSION_ID}';
        });
    }

    public function test_rejects_a_paid_session_with_an_amount_mismatch(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions/cs_test_123' => Http::response([
                'id' => 'cs_test_123',
                'status' => 'complete',
                'mode' => 'payment',
                'status' => 'complete',
                'mode' => 'payment',
                'payment_status' => 'paid',
                'client_reference_id' => 'order-123',
                'metadata' => ['order_id' => 'order-123'],
                'amount_total' => 2744,
                'currency' => 'usd',
                'payment_intent' => 'pi_test_123',
            ]),
        ]);

        $order = new Order;
        $order->setAttribute('_id', 'order-123');
        $order->total = 27.45;

        $this->expectException(\InvalidArgumentException::class);
        app(StripePaymentService::class)->verifyPaidSession('cs_test_123', $order);
    }

    public function test_verifies_a_paid_session_for_the_exact_order_and_amount(): void
    {
        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions/cs_test_123' => Http::response([
                'id' => 'cs_test_123',
                'status' => 'complete',
                'mode' => 'payment',
                'payment_status' => 'paid',
                'client_reference_id' => 'order-123',
                'metadata' => ['order_id' => 'order-123'],
                'amount_total' => 2745,
                'currency' => 'usd',
                'payment_intent' => 'pi_test_123',
            ]),
        ]);

        $order = new Order;
        $order->setAttribute('_id', 'order-123');
        $order->total = 27.45;

        $result = app(StripePaymentService::class)->verifyPaidSession('cs_test_123', $order);

        $this->assertSame('pi_test_123', $result['payment_intent']);
    }
}
