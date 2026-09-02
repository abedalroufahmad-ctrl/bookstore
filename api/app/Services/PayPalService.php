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
     * @param  list<array{
     *     amount: string,
     *     custom_id: string,
     *     reference_id?: string,
     *     payee_email?: string|null,
     *     payee_merchant_id?: string|null
     * }>  $units
     * @return array{approval_url: string|null, id: string|null, raw: array<string, mixed>}
     */
    public function createCheckoutOrder(
        array $units,
        string $currencyCode,
        string $returnUrl,
        string $cancelUrl,
        bool $routeToPublisherPayee
    ): array {
        $brand = (string) config('app.name', 'Book Store');
        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => $this->buildPurchaseUnits($units, strtoupper($currencyCode), $routeToPublisherPayee),
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'brand_name' => $brand,
                'user_action' => 'PAY_NOW',
            ],
        ];

        $response = $this->authorizedPost('/v2/checkout/orders', $payload);
        if (! $response->successful()) {
            Log::warning('PayPal create order failed', [
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
                'route_to_publisher' => $routeToPublisherPayee,
            ]);

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

    /**
     * @param  list<array{
     *     amount: string,
     *     custom_id: string,
     *     reference_id?: string,
     *     payee_email?: string|null,
     *     payee_merchant_id?: string|null
     * }>  $units
     * @return list<array<string, mixed>>
     */
    private function buildPurchaseUnits(array $units, string $currency, bool $routeToPublisherPayee): array
    {
        if ($units === []) {
            throw new \InvalidArgumentException('PayPal order has no purchase units.');
        }

        if (! $routeToPublisherPayee) {
            return [$this->combinedUnit($units, $currency, null)];
        }

        $payeeKeys = [];
        foreach ($units as $unit) {
            $payeeKeys[] = trim((string) ($unit['payee_email'] ?? '')).'|'.trim((string) ($unit['payee_merchant_id'] ?? ''));
        }
        $unique = array_values(array_unique($payeeKeys));
        if (count($unique) <= 1) {
            return [$this->combinedUnit($units, $currency, $this->payeePayload($units[0]))];
        }

        $out = [];
        foreach ($units as $index => $unit) {
            $reference = (string) ($unit['reference_id'] ?? $unit['custom_id'] ?? (string) $index);
            $row = [
                'reference_id' => substr($reference, 0, 256),
                'custom_id' => substr((string) $unit['custom_id'], 0, 127),
                'amount' => [
                    'currency_code' => $currency,
                    'value' => $unit['amount'],
                ],
            ];
            $payee = $this->payeePayload($unit);
            if ($payee !== null) {
                $row['payee'] = $payee;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param  list<array{amount: string, custom_id: string, payee_email?: string|null, payee_merchant_id?: string|null}>  $units
     * @param  array<string, string>|null  $payee
     * @return array<string, mixed>
     */
    private function combinedUnit(array $units, string $currency, ?array $payee): array
    {
        $total = 0.0;
        $ids = [];
        foreach ($units as $unit) {
            $total += (float) $unit['amount'];
            $ids[] = (string) $unit['custom_id'];
        }

        $row = [
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format($total, 2, '.', ''),
            ],
            'custom_id' => substr(implode(',', $ids), 0, 127),
        ];
        if ($payee !== null) {
            $row['payee'] = $payee;
        }

        return $row;
    }

    /**
     * @param  array{payee_email?: string|null, payee_merchant_id?: string|null}  $unit
     * @return array<string, string>|null
     */
    private function payeePayload(array $unit): ?array
    {
        $payload = [];
        $email = trim((string) ($unit['payee_email'] ?? ''));
        $merchantId = trim((string) ($unit['payee_merchant_id'] ?? ''));
        if ($email !== '') {
            $payload['email_address'] = $email;
        }
        if ($merchantId !== '') {
            $payload['merchant_id'] = $merchantId;
        }

        return $payload === [] ? null : $payload;
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
