<?php

namespace App\Services;

use App\Models\Order;
use App\Support\CurrencyAmount;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StripePaymentService
{
    /**
     * @return array{id: string, url: string}
     */
    public function createCheckoutSession(Order $order, int $expiresAt): array
    {
        $successUrl = (string) config('services.stripe.checkout_success_url');
        $cancelUrl = (string) config('services.stripe.checkout_cancel_url');
        if ($successUrl === '' || $cancelUrl === '') {
            throw new \RuntimeException('Stripe checkout redirect URLs are not configured.');
        }

        $orderId = (string) $order->getKey();
        $currencyCode = strtoupper((string) ($order->currency_code ?? config('services.stripe.currency', 'USD')));
        $response = $this->request()
            ->withHeaders(['Idempotency-Key' => 'order-'.$orderId.'-'.$expiresAt])
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'expires_at' => $expiresAt,
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currencyCode),
                        'unit_amount' => CurrencyAmount::minorUnits((float) $order->total, $currencyCode),
                        'product_data' => ['name' => 'Order '.$orderId],
                    ],
                    'quantity' => 1,
                ]],
                'client_reference_id' => $orderId,
                'metadata' => ['order_id' => $orderId],
                'payment_intent_data' => ['metadata' => ['order_id' => $orderId]],
                'success_url' => str_replace('{ORDER_ID}', rawurlencode($orderId), $successUrl),
                'cancel_url' => str_replace('{ORDER_ID}', rawurlencode($orderId), $cancelUrl),
            ]);

        $data = $this->validatedResponse($response, 'create checkout session');
        $id = $data['id'] ?? null;
        $url = $data['url'] ?? null;
        if (! is_string($id) || $id === '' || ! is_string($url) || $url === '') {
            throw new \RuntimeException('Stripe did not return a checkout session URL.');
        }

        return ['id' => $id, 'url' => $url];
    }

    /**
     * @return array{payment_intent: string}
     */
    public function verifyPaidSession(string $sessionId, Order $order): array
    {
        $data = $this->validatedResponse(
            $this->request()->get('https://api.stripe.com/v1/checkout/sessions/'.rawurlencode($sessionId)),
            'retrieve checkout session'
        );
        $expectedCurrency = strtoupper((string) ($order->currency_code ?? config('services.stripe.currency', 'USD')));
        $expectedAmount = CurrencyAmount::minorUnits((float) $order->total, $expectedCurrency);
        $paymentIntent = $data['payment_intent'] ?? null;

        if (
            ($data['id'] ?? null) !== $sessionId
            || ($data['status'] ?? null) !== 'complete'
            || ($data['mode'] ?? null) !== 'payment'
            || ($data['payment_status'] ?? null) !== 'paid'
            || (string) ($data['client_reference_id'] ?? '') !== (string) $order->getKey()
            || (string) ($data['metadata']['order_id'] ?? '') !== (string) $order->getKey()
            || (int) ($data['amount_total'] ?? -1) !== $expectedAmount
            || strtoupper((string) ($data['currency'] ?? '')) !== $expectedCurrency
            || ! is_string($paymentIntent)
            || $paymentIntent === ''
        ) {
            throw new \InvalidArgumentException('Stripe payment could not be verified for this order.');
        }

        return ['payment_intent' => $paymentIntent];
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $secret = trim((string) config('services.stripe.secret'));
        if ($secret === '') {
            throw new \RuntimeException('Stripe secret key is not configured.');
        }

        return Http::timeout(20)
            ->connectTimeout(5)
            ->withToken($secret)
            ->acceptJson();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedResponse(Response $response, string $operation): array
    {
        if (! $response->successful()) {
            Log::warning('Stripe API request failed', [
                'operation' => $operation,
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ]);

            throw new \RuntimeException('Stripe could not '.$operation.'.');
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new \RuntimeException('Stripe returned an invalid response.');
        }

        return $data;
    }
}
