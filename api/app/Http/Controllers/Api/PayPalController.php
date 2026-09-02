<?php

namespace App\Http\Controllers\Api;

use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Http\Requests\Order\PayPalQuotedOrdersRequest;
use App\Models\Order;
use App\Services\PayPalService;
use App\Services\PublisherPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PayPalController extends BaseApiController
{
    public function __construct(
        private OrderServiceInterface $orderService,
        private PayPalService $payPalService,
        private PublisherPayoutService $publisherPayoutService
    ) {}

    public function start(PayPalQuotedOrdersRequest $request): JsonResponse
    {
        $returnUrl = (string) config('paypal.return_url');
        $cancelUrl = (string) config('paypal.cancel_redirect');
        if ($returnUrl === '' || $cancelUrl === '') {
            return $this->errorResponse('PayPal redirect URLs are not configured on the server.', 503);
        }

        /** @var list<string> $orderIds */
        $orderIds = $request->validated('order_ids');

        try {
            $orders = [];
            foreach ($orderIds as $orderId) {
                $order = Order::find($orderId);
                if (! $order) {
                    return $this->errorResponse('Order not found.', 404);
                }
                $orders[] = $order;
            }

            $customId = implode(',', $orderIds);
            if (strlen($customId) > 127) {
                return $this->errorResponse('Too many orders for a single PayPal payment.', 422);
            }

            $units = [];
            foreach ($orders as $order) {
                $payee = $this->publisherPayoutService->paypalPayeeForOrder($order);
                $units[] = [
                    'amount' => number_format((float) $order->total, 2, '.', ''),
                    'custom_id' => (string) $order->getKey(),
                    'reference_id' => (string) $order->getKey(),
                    'payee_email' => $payee['email'],
                    'payee_merchant_id' => $payee['merchant_id'],
                ];
            }

            try {
                $created = $this->payPalService->createCheckoutOrder(
                    $units,
                    (string) config('paypal.currency'),
                    $returnUrl,
                    $cancelUrl,
                    true
                );
            } catch (\Throwable $payeeError) {
                Log::warning('PayPal publisher payee rejected; collecting on platform account', [
                    'message' => $payeeError->getMessage(),
                ]);
                $created = $this->payPalService->createCheckoutOrder(
                    $units,
                    (string) config('paypal.currency'),
                    $returnUrl,
                    $cancelUrl,
                    false
                );
            }
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return $this->errorResponse(
                config('app.debug') ? $e->getMessage() : 'Could not start PayPal checkout.',
                502
            );
        }

        if ($created['approval_url'] === null || $created['id'] === null) {
            return $this->errorResponse('PayPal did not return an approval link.', 502);
        }

        return $this->successResponse([
            'orders' => array_map(fn (Order $order) => $order->hideInternalPayouts(), $orders),
            'count' => count($orders),
            'paypal_order_id' => $created['id'],
            'approval_url' => $created['approval_url'],
        ], 'Redirect to approval_url to complete payment.', 200);
    }

    /**
     * Public redirect target after buyer approves payment in PayPal (return_url).
     */
    public function complete(Request $request): RedirectResponse|Response
    {
        $token = $request->query('token');
        if (! is_string($token) || $token === '') {
            return $this->redirectToConfiguredUrl((string) config('paypal.cancel_redirect'), 'paypal=missing_token');
        }

        $successUrl = (string) config('paypal.success_redirect');
        $failUrl = (string) config('paypal.cancel_redirect');

        try {
            $details = $this->payPalService->getOrder($token);
            $status = is_string($details['status'] ?? null) ? $details['status'] : '';

            if ($status === 'APPROVED') {
                $details = $this->payPalService->captureOrder($token);
                $status = is_string($details['status'] ?? null) ? $details['status'] : '';
            }

            if ($status !== 'COMPLETED') {
                return $this->redirectToConfiguredUrl($failUrl, 'paypal=not_completed');
            }

            [$customId, $captureId, $capturedAmount] = $this->extractCustomCaptureAndAmount($details);
            if ($customId === '') {
                Log::warning('PayPal completed order missing custom_id');

                return $this->redirectToConfiguredUrl($failUrl, 'paypal=no_custom_id');
            }

            $ids = array_values(array_filter(array_map('trim', explode(',', $customId))));
            $expected = 0.0;
            foreach ($ids as $orderId) {
                $order = Order::find($orderId);
                if (! $order) {
                    Log::warning('PayPal capture referenced missing order', ['order_id' => $orderId]);

                    return $this->redirectToConfiguredUrl($failUrl, 'paypal=order_mismatch');
                }
                $expected += (float) $order->total;
            }
            $expected = round($expected, 2);
            if ($capturedAmount === null || abs($capturedAmount - $expected) > 0.01) {
                Log::warning('PayPal capture amount mismatch', [
                    'expected' => $expected,
                    'captured' => $capturedAmount,
                    'order_ids' => $ids,
                ]);

                return $this->redirectToConfiguredUrl($failUrl, 'paypal=amount_mismatch');
            }

            $this->orderService->markPayPalOrdersPaid($ids, $captureId);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return $this->redirectToConfiguredUrl($failUrl, 'paypal=error');
        }

        if ($successUrl === '') {
            return response('Payment successful.', 200);
        }

        $sep = str_contains($successUrl, '?') ? '&' : '?';

        return redirect()->away($successUrl.$sep.'paypal=ok');
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @return array{0: string, 1: string|null, 2: float|null}
     */
    private function extractCustomCaptureAndAmount(array $orderPayload): array
    {
        $units = $orderPayload['purchase_units'] ?? [];
        if (! is_array($units) || $units === []) {
            return ['', null, null];
        }

        $ids = [];
        $captureId = null;
        $capturedAmount = 0.0;
        $hasAmount = false;
        foreach ($units as $pu) {
            if (! is_array($pu)) {
                continue;
            }
            $customId = is_string($pu['custom_id'] ?? null) ? $pu['custom_id'] : '';
            if ($customId !== '') {
                foreach (explode(',', $customId) as $id) {
                    $id = trim($id);
                    if ($id !== '') {
                        $ids[] = $id;
                    }
                }
            }
            $payments = $pu['payments'] ?? null;
            if (! is_array($payments)) {
                continue;
            }
            $captures = $payments['captures'] ?? [];
            if (! is_array($captures)) {
                continue;
            }
            foreach ($captures as $capture) {
                if (! is_array($capture)) {
                    continue;
                }
                if ($captureId === null && is_string($capture['id'] ?? null)) {
                    $captureId = $capture['id'];
                }
                $amountValue = $capture['amount']['value'] ?? null;
                if (is_numeric($amountValue)) {
                    $capturedAmount += (float) $amountValue;
                    $hasAmount = true;
                }
            }
        }

        $ids = array_values(array_unique($ids));

        return [implode(',', $ids), $captureId, $hasAmount ? round($capturedAmount, 2) : null];
    }

    private function redirectToConfiguredUrl(string $base, string $querySuffix): RedirectResponse
    {
        if ($base === '') {
            return redirect()->away('/?'.$querySuffix);
        }
        $sep = str_contains($base, '?') ? '&' : '?';

        return redirect()->away($base.$sep.$querySuffix);
    }
}
