<?php

namespace App\Http\Controllers\Api;

use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Payment gateway webhooks. Validate signatures in production and call markOrderPaymentPaid.
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

        if ($secret && $sig && ! $this->verifyStripeSignature($payload, $sig, $secret)) {
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
        $payload = $request->all();
        // In production: verify PayPal webhook signature (see PayPal API docs).
        $eventType = $payload['event_type'] ?? '';
        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $resource = $payload['resource'] ?? [];
            $orderId = $resource['custom_id'] ?? $resource['supplementary_data']['related_ids']['order_id'] ?? null;
            $transactionId = $resource['id'] ?? null;
            if ($orderId) {
                $this->orderService->markOrderPaymentPaid($orderId, $transactionId);
            }
        }

        return $this->successResponse(null, 'OK');
    }

    private function verifyStripeSignature(string $payload, string $sig, string $secret): bool
    {
        if (! class_exists(\Stripe\Webhook::class)) {
            return false;
        }
        try {
            \Stripe\Webhook::constructEvent($payload, $sig, $secret);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
