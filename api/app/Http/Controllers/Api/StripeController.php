<?php

namespace App\Http\Controllers\Api;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Order;
use App\Services\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeController extends BaseApiController
{
    public function __construct(
        private OrderServiceInterface $orderService,
        private StripePaymentService $stripePaymentService
    ) {}

    public function start(string $id): JsonResponse
    {
        $customer = auth('customer')->user();
        $order = $this->orderService->getOrderById($id, (string) $customer->getKey());
        if (! $order) {
            return $this->errorResponse('Order not found.', 404);
        }

        if (
            OrderStatus::normalizeStored((string) $order->status) !== OrderStatus::AwaitingCustomerConfirmation
            || ! in_array((string) ($order->payment_method ?? ''), ['stripe', 'card'], true)
        ) {
            return $this->errorResponse('This order is not ready for card payment.', 422);
        }

        try {
            $expiresAt = (int) ($order->stripe_checkout_session_expires_at ?? 0);
            if ($expiresAt <= now()->timestamp) {
                $expiresAt = now()->addMinutes(31)->timestamp;
            }
            $session = $this->stripePaymentService->createCheckoutSession($order, $expiresAt);
            $order->stripe_checkout_session_id = $session['id'];
            $order->stripe_checkout_session_expires_at = $expiresAt;
            $order->save();
        } catch (\Throwable $e) {
            Log::error('Could not start Stripe order checkout', [
                'order_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                config('app.debug') ? $e->getMessage() : 'Could not start card payment.',
                502
            );
        }

        return $this->successResponse([
            'checkout_url' => $session['url'],
        ], 'Redirect to Stripe Checkout to pay.');
    }

    public function verify(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => ['required', 'string', 'max:255'],
        ]);
        $customer = auth('customer')->user();
        $order = $this->orderService->getOrderById($id, (string) $customer->getKey());
        if (! $order) {
            return $this->errorResponse('Order not found.', 404);
        }

        if (
            ! hash_equals((string) ($order->stripe_checkout_session_id ?? ''), $validated['session_id'])
            || ! in_array((string) ($order->payment_method ?? ''), ['stripe', 'card'], true)
        ) {
            return $this->errorResponse('Stripe checkout session does not match this order.', 403);
        }

        try {
            $verified = $this->stripePaymentService->verifyPaidSession($validated['session_id'], $order);
            $this->orderService->markOrderPaymentPaid($id, $verified['payment_intent']);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('Could not verify Stripe order payment', [
                'order_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                config('app.debug') ? $e->getMessage() : 'Could not verify card payment.',
                502
            );
        }

        $order = $this->orderService->getOrderById($id, (string) $customer->getKey());

        return $this->successResponse($order?->hideInternalPayouts(), 'Card payment completed.');
    }
}
