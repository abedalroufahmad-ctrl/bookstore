<?php

namespace App\Http\Controllers\Api;

use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Http\Requests\Order\PayPalQuotedOrdersRequest;
use App\Models\Order;
use App\Services\PayPalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PayPalController extends BaseApiController
{
    public function __construct(
        private OrderServiceInterface $orderService,
        private PayPalService $payPalService
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

            $total = 0.0;
            foreach ($orders as $order) {
                $total += (float) $order->total;
            }
            $amount = number_format($total, 2, '.', '');

            $created = $this->payPalService->createOrder(
                $amount,
                (string) config('paypal.currency'),
                $customId,
                $returnUrl,
                $cancelUrl
            );
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
            'orders' => $orders,
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

            [$customId, $captureId] = $this->extractCustomAndCaptureId($details);
            if ($customId === '') {
                Log::warning('PayPal completed order missing custom_id');

                return $this->redirectToConfiguredUrl($failUrl, 'paypal=no_custom_id');
            }

            $ids = array_filter(array_map('trim', explode(',', $customId)));
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
     * @return array{0: string, 1: string|null}
     */
    private function extractCustomAndCaptureId(array $orderPayload): array
    {
        $units = $orderPayload['purchase_units'] ?? [];
        if (! is_array($units) || $units === []) {
            return ['', null];
        }

        $pu = $units[0];
        if (! is_array($pu)) {
            return ['', null];
        }

        $customId = is_string($pu['custom_id'] ?? null) ? $pu['custom_id'] : '';
        $captureId = null;
        $payments = $pu['payments'] ?? null;
        if (is_array($payments)) {
            $captures = $payments['captures'] ?? [];
            if (is_array($captures) && $captures !== [] && is_array($captures[0])) {
                $c0 = $captures[0];
                if (is_string($c0['id'] ?? null)) {
                    $captureId = $c0['id'];
                }
            }
        }

        return [$customId, $captureId];
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
