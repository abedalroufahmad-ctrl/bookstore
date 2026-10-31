<?php

namespace App\Http\Controllers\Api;

use App\Domain\Order\Interfaces\OrderServiceInterface;
use App\Http\Requests\Order\PayPalQuotedOrdersRequest;
use App\Models\Order;
use App\Services\PayPalService;
use App\Services\PublisherPayoutService;
use App\Support\CurrencyAmount;
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
        $orderRedirect = (string) config('paypal.order_redirect');
        if ($returnUrl === '' || $orderRedirect === '') {
            return $this->errorResponse('PayPal redirect URLs are not configured on the server.', 503);
        }

        /** @var list<string> $orderIds */
        $orderIds = $request->validated('order_ids');

        try {
            $ordersById = $this->loadOrdersById($orderIds);
            $orders = [];
            foreach ($orderIds as $orderId) {
                $order = $ordersById[(string) $orderId] ?? null;
                if (! $order) {
                    return $this->errorResponse('Order not found.', 404);
                }
                $orders[] = $order;
            }
            $currencyCode = strtoupper((string) ($orders[0]->currency_code ?? config('paypal.currency', 'USD')));
            foreach ($orders as $order) {
                if (strtoupper((string) ($order->currency_code ?? config('paypal.currency', 'USD'))) !== $currencyCode) {
                    return $this->errorResponse('PayPal orders must use the same currency.', 422);
                }
            }

            $customId = implode(',', $orderIds);
            if (strlen($customId) > 127) {
                return $this->errorResponse('Too many orders for a single PayPal payment.', 422);
            }

            $returnUrl = $this->appendQuery($returnUrl, 'order_id', (string) $orders[0]->getKey());
            $cancelUrl = str_replace(
                '{ORDER_ID}',
                rawurlencode((string) $orders[0]->getKey()),
                $orderRedirect
            );
            $cancelUrl = $this->appendQuery($cancelUrl, 'paypal', 'cancelled');

            $units = [];
            foreach ($orders as $order) {
                $payee = $this->publisherPayoutService->paypalPayeeForOrder($order);
                $units[] = [
                    'amount' => CurrencyAmount::decimalString((float) $order->total, $currencyCode),
                    'custom_id' => (string) $order->getKey(),
                    'reference_id' => (string) $order->getKey(),
                    'payee_email' => $payee['email'],
                    'payee_merchant_id' => $payee['merchant_id'],
                ];
            }

            try {
                $created = $this->payPalService->createCheckoutOrder(
                    $units,
                    $currencyCode,
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
                    $currencyCode,
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

    public function simulateSandboxPayment(string $id): JsonResponse
    {
        if ($this->payPalService->mode() !== 'sandbox') {
            return $this->errorResponse('Simulated payments are only available in PayPal Sandbox.', 403);
        }

        $customer = auth('customer')->user();
        $order = $customer
            ? $this->orderService->getOrderById($id, (string) $customer->getKey())
            : null;
        if (! $order) {
            return $this->errorResponse('Order not found.', 404);
        }

        try {
            $this->orderService->markSandboxPayPalOrderPaid($order);
        } catch (\InvalidArgumentException $e) {
            return $this->domainErrorResponse($e);
        }

        $order->refresh();

        return $this->successResponse(
            $order->hideInternalPayouts(),
            'Sandbox payment simulated. No PayPal transaction was created.'
        );
    }

    /**
     * Public redirect target after buyer approves payment in PayPal (return_url).
     */
    public function complete(Request $request): RedirectResponse|Response
    {
        $token = $request->query('token');
        if (! is_string($token) || $token === '') {
            return $this->redirectToOrder($request->query('order_id'), 'cancelled');
        }

        $successUrl = (string) config('paypal.success_redirect');
        $fallbackOrderId = $request->query('order_id');

        try {
            $details = $this->payPalService->getOrder($token);
            $customId = $this->extractOrderIds($details);
            $orderId = $customId[0] ?? $fallbackOrderId;
            $status = is_string($details['status'] ?? null) ? $details['status'] : '';

            if ($status === 'APPROVED') {
                $details = $this->payPalService->captureOrder($token);
                $status = is_string($details['status'] ?? null) ? $details['status'] : '';
            }

            if ($status !== 'COMPLETED') {
                return $this->redirectToOrder($orderId, 'not_completed');
            }

            [$customId, $captureId, $capturedAmount, $capturedCurrency] = $this->extractCustomCaptureAndAmount($details);
            if ($customId === '') {
                Log::warning('PayPal completed order missing custom_id');

                return $this->redirectToOrder($orderId, 'no_custom_id');
            }

            $ids = array_values(array_filter(array_map('trim', explode(',', $customId))));
            $expected = 0.0;
            $expectedCurrency = null;
            $ordersById = $this->loadOrdersById($ids);
            foreach ($ids as $orderId) {
                $order = $ordersById[$orderId] ?? null;
                if (! $order) {
                    Log::warning('PayPal capture referenced missing order', ['order_id' => $orderId]);

                    return $this->redirectToOrder($orderId, 'order_mismatch');
                }
                $orderCurrency = strtoupper((string) ($order->currency_code ?? config('paypal.currency', 'USD')));
                if ($expectedCurrency !== null && $expectedCurrency !== $orderCurrency) {
                    Log::warning('PayPal payment contains orders with different currencies', ['order_ids' => $ids]);

                    return $this->redirectToOrder($orderId, 'currency_mismatch');
                }
                $expectedCurrency = $orderCurrency;
                $expected += (float) $order->total;
            }
            $expectedCurrency ??= strtoupper((string) config('paypal.currency', 'USD'));
            $expected = CurrencyAmount::round($expected, $expectedCurrency);
            $tolerance = 1 / (10 ** CurrencyAmount::precision($expectedCurrency));
            if (
                $capturedAmount === null
                || $capturedCurrency !== $expectedCurrency
                || abs($capturedAmount - $expected) >= $tolerance
            ) {
                Log::warning('PayPal capture amount mismatch', [
                    'expected' => $expected,
                    'captured' => $capturedAmount,
                    'order_ids' => $ids,
                ]);

                return $this->redirectToOrder($orderId, 'amount_mismatch');
            }

            $this->orderService->markPayPalOrdersPaid($ids, $captureId);
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), ['exception' => $e]);

            return $this->redirectToOrder($fallbackOrderId, 'error');
        }

        if ($successUrl === '') {
            return response('Payment successful.', 200);
        }

        $sep = str_contains($successUrl, '?') ? '&' : '?';

        return redirect()->away($successUrl.$sep.'paypal=ok');
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @return array{0: string, 1: string|null, 2: float|null, 3: string|null}
     */
    private function extractCustomCaptureAndAmount(array $orderPayload): array
    {
        $units = $orderPayload['purchase_units'] ?? [];
        if (! is_array($units) || $units === []) {
            return ['', null, null, null];
        }

        $ids = [];
        $captureId = null;
        $capturedAmount = 0.0;
        $hasAmount = false;
        $capturedCurrency = null;
        $hasMixedCurrencies = false;
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
                $currency = $capture['amount']['currency_code'] ?? null;
                if (is_string($currency) && $currency !== '') {
                    $currency = strtoupper($currency);
                    if ($capturedCurrency !== null && $capturedCurrency !== $currency) {
                        $hasMixedCurrencies = true;
                    }
                    $capturedCurrency = $currency;
                }
                if (is_numeric($amountValue)) {
                    $capturedAmount += (float) $amountValue;
                    $hasAmount = true;
                }
            }
        }

        $ids = array_values(array_unique($ids));

        return [
            implode(',', $ids),
            $captureId,
            $hasAmount && $capturedCurrency !== null
                ? CurrencyAmount::round($capturedAmount, $capturedCurrency)
                : null,
            $hasMixedCurrencies ? null : $capturedCurrency,
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, Order>
     */
    private function loadOrdersById(array $ids): array
    {
        $byId = [];
        foreach (Order::query()->findMany(array_map('strval', $ids)) as $order) {
            $byId[(string) $order->getKey()] = $order;
        }

        return $byId;
    }

    private function redirectToOrder(mixed $orderId, string $result): RedirectResponse
    {
        $base = trim((string) config('paypal.order_redirect'));
        if ($base === '' || ! is_string($orderId) || $orderId === '') {
            $base = rtrim((string) config('paypal.success_redirect'), '/');
            if ($base === '') {
                return redirect()->away('/orders?paypal='.$result);
            }
            $base .= '?paypal='.$result;

            return redirect()->away($base);
        }

        $url = str_replace('{ORDER_ID}', rawurlencode($orderId), $base);

        return redirect()->away($this->appendQuery($url, 'paypal', $result));
    }

    /**
     * @param  array<string, mixed>  $orderPayload
     * @return list<string>
     */
    private function extractOrderIds(array $orderPayload): array
    {
        $units = $orderPayload['purchase_units'] ?? [];
        if (! is_array($units)) {
            return [];
        }

        $ids = [];
        foreach ($units as $unit) {
            if (! is_array($unit) || ! is_string($unit['custom_id'] ?? null)) {
                continue;
            }
            foreach (explode(',', $unit['custom_id']) as $id) {
                $id = trim($id);
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    private function appendQuery(string $url, string $key, string $value): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.rawurlencode($key).'='.rawurlencode($value);
    }
}
