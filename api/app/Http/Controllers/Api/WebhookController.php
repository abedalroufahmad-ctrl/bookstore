<?php

namespace App\Http\Controllers\Api;

use App\Domain\Order\Interfaces\OrderServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Stripe\Webhook;

/**
 * Payment gateway webhooks. Fail closed when signatures cannot be verified.
 * Do not store credit card details.
 */
class WebhookController extends BaseApiController
{
    public function __construct(
        private OrderServiceInterface $orderService
    ) {}

    public function stripe(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sig = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (! is_string($secret) || $secret === '') {
            Log::error('Stripe webhook rejected: STRIPE_WEBHOOK_SECRET is not configured');

            return $this->errorResponse('Invalid signature', 400);
        }

        if (! is_string($sig) || $sig === '') {
            return $this->errorResponse('Invalid signature', 400);
        }

        if (! $this->verifyStripeSignature($payload, $sig, $secret)) {
            Log::warning('Stripe webhook signature verification failed');

            return $this->errorResponse('Invalid signature', 400);
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return $this->errorResponse('Invalid payload', 400);
        }

        $type = $data['type'] ?? '';
        if ($type === 'payment_intent.succeeded') {
            $object = $data['data']['object'] ?? [];
            $orderId = $object['metadata']['order_id'] ?? null;
            $transactionId = $object['id'] ?? null;
            if ($orderId) {
                $this->orderService->markOrderPaymentPaid($orderId, $transactionId);
            }
        }

        return $this->successResponse(null, 'OK');
    }

    public function paypal(Request $request): JsonResponse
    {
        if (! $this->verifyPayPalWebhook($request)) {
            Log::warning('PayPal webhook signature verification failed or not configured');

            return $this->errorResponse('Invalid signature', 400);
        }

        $payload = $request->all();
        $eventType = $payload['event_type'] ?? '';
        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $resource = $payload['resource'] ?? [];
            if (! is_array($resource)) {
                return $this->successResponse(null, 'OK');
            }

            $customId = $resource['custom_id'] ?? null;
            if (! is_string($customId) || $customId === '') {
                $customId = null;
            }
            $transactionId = is_string($resource['id'] ?? null) ? $resource['id'] : null;
            if (is_string($customId) && $customId !== '') {
                $ids = array_filter(array_map('trim', explode(',', $customId)));
                try {
                    $this->orderService->markPayPalOrdersPaid($ids, $transactionId);
                } catch (\Throwable $e) {
                    Log::warning('PayPal webhook could not mark orders paid', ['message' => $e->getMessage()]);
                }
            }
        }

        return $this->successResponse(null, 'OK');
    }

    private function verifyStripeSignature(string $payload, string $sig, string $secret): bool
    {
        if (! class_exists(Webhook::class)) {
            // Without Stripe SDK, require HMAC header match against raw payload timestamp scheme is unavailable.
            return false;
        }
        try {
            Webhook::constructEvent($payload, $sig, $secret);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Verify PayPal webhook via PayPal Verify Webhook Signature API.
     * Requires PAYPAL_WEBHOOK_ID + PayPal client credentials.
     */
    private function verifyPayPalWebhook(Request $request): bool
    {
        $webhookId = config('services.paypal.webhook_id');
        $clientId = config('paypal.client_id');
        $clientSecret = config('paypal.secret');
        $mode = config('paypal.mode', 'sandbox');

        if (! is_string($webhookId) || $webhookId === '') {
            return false;
        }
        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            return false;
        }

        $transmissionId = $request->header('PAYPAL-TRANSMISSION-ID');
        $transmissionTime = $request->header('PAYPAL-TRANSMISSION-TIME');
        $certUrl = $request->header('PAYPAL-CERT-URL');
        $authAlgo = $request->header('PAYPAL-AUTH-ALGO');
        $transmissionSig = $request->header('PAYPAL-TRANSMISSION-SIG');

        if (! $transmissionId || ! $transmissionTime || ! $certUrl || ! $authAlgo || ! $transmissionSig) {
            return false;
        }

        $base = $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';

        try {
            $tokenRes = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post("{$base}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

            if (! $tokenRes->successful()) {
                return false;
            }

            $accessToken = $tokenRes->json('access_token');
            if (! is_string($accessToken) || $accessToken === '') {
                return false;
            }

            $verifyRes = Http::withToken($accessToken)
                ->acceptJson()
                ->post("{$base}/v1/notifications/verify-webhook-signature", [
                    'auth_algo' => $authAlgo,
                    'cert_url' => $certUrl,
                    'transmission_id' => $transmissionId,
                    'transmission_sig' => $transmissionSig,
                    'transmission_time' => $transmissionTime,
                    'webhook_id' => $webhookId,
                    'webhook_event' => $request->all(),
                ]);

            return $verifyRes->successful()
                && ($verifyRes->json('verification_status') === 'SUCCESS');
        } catch (\Throwable $e) {
            Log::error('PayPal webhook verification error', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
