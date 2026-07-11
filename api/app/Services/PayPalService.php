<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    public function baseUrl(): string
    {
        return config('paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function getAccessToken(): string
    {
        $clientId = trim((string) config('paypal.client_id'));
        $secret = trim((string) config('paypal.secret'));
        if ($clientId === '' || $secret === '') {
            throw new \RuntimeException('PayPal client credentials are not configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $secret)
            ->post($this->baseUrl().'/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

        if (! $response->successful()) {
            Log::warning('PayPal OAuth request failed', [
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
                'mode' => config('paypal.mode'),
                'base_url' => $this->baseUrl(),
            ]);

            $desc = $response->json('error_description');
            if ($response->status() === 401 && is_string($desc) && $desc !== '') {
                throw new \RuntimeException(
                    'PayPal rejected the Client ID or Secret. In developer.paypal.com open Sandbox → your REST app and copy both values from the same app (or regenerate the secret).'
                );
            }

            throw new \RuntimeException('PayPal authentication failed.');
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('PayPal authentication returned no token.');
        }

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(string $paypalOrderId): array
    {
        $response = $this->authorizedGet('/v2/checkout/orders/'.rawurlencode($paypalOrderId));
        if (! $response->successful()) {
            Log::warning('PayPal get order failed', ['status' => $response->status()]);

            throw new \RuntimeException('Could not load PayPal order.');
        }

        /** @var array<string, mixed> */
        return $response->json();
    }

    /**
     * @return array{approval_url: string|null, id: string|null, raw: array<string, mixed>}
     */
    public function createOrder(
        string $amountValue,
        string $currencyCode,
        string $customId,
        string $returnUrl,
        string $cancelUrl
    ): array {
        $brand = (string) config('app.name', 'Book Store');

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => strtoupper($currencyCode),
                        'value' => $amountValue,
                    ],
                    'custom_id' => $customId,
                ],
            ],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'brand_name' => $brand,
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = $this->authorizedPost('/v2/checkout/orders', $payload);
        if (! $response->successful()) {
            Log::warning('PayPal create order failed', ['status' => $response->status()]);

            throw new \RuntimeException('Could not create PayPal order.');
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        $approvalUrl = null;
        $links = $data['links'] ?? [];
        if (is_array($links)) {
            foreach ($links as $link) {
                if (! is_array($link)) {
                    continue;
                }
                if (($link['rel'] ?? '') === 'approve' && isset($link['href']) && is_string($link['href'])) {
                    $approvalUrl = $link['href'];
                    break;
                }
            }
        }

        $id = isset($data['id']) && is_string($data['id']) ? $data['id'] : null;

        return ['approval_url' => $approvalUrl, 'id' => $id, 'raw' => $data];
    }

    /**
     * @return array<string, mixed>
     */
    public function captureOrder(string $paypalOrderId): array
    {
        $response = $this->authorizedPost(
            '/v2/checkout/orders/'.rawurlencode($paypalOrderId).'/capture',
            new \stdClass
        );
        if (! $response->successful()) {
            Log::warning('PayPal capture failed', ['status' => $response->status()]);

            throw new \RuntimeException('PayPal capture failed.');
        }

        /** @var array<string, mixed> */
        return $response->json();
    }

    private function authorizedGet(string $path): Response
    {
        return Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->get($this->baseUrl().$path);
    }

    /**
     * @param  array<string, mixed>|\stdClass  $body
     */
    private function authorizedPost(string $path, array|\stdClass $body): Response
    {
        return Http::withToken($this->getAccessToken())
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl().$path, $body);
    }
}
