<?php

namespace App\Http\Requests\Order;

use App\Domain\Order\Enums\OrderStatus;
use App\Http\Requests\BaseFormRequest;
use App\Models\Order;
use Illuminate\Validation\Validator;

class PayPalQuotedOrdersRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return auth('customer')->check();
    }

    public function rules(): array
    {
        return [
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $customer = auth('customer')->user();
            if (! $customer) {
                return;
            }
            /** @var list<string>|null $ids */
            $ids = $this->input('order_ids');
            if (! is_array($ids) || $ids === []) {
                return;
            }

            foreach ($ids as $id) {
                $order = Order::find((string) $id);
                if (! $order) {
                    $validator->errors()->add('order_ids', 'One or more orders were not found.');

                    return;
                }
                if ((string) $order->customer_id !== (string) $customer->getKey()) {
                    $validator->errors()->add('order_ids', 'Orders must belong to the authenticated customer.');

                    return;
                }
                $st = OrderStatus::normalizeStored((string) $order->status);
                if ($st !== OrderStatus::AwaitingCustomerConfirmation) {
                    $validator->errors()->add('order_ids', 'Orders must be waiting for customer confirmation before PayPal payment.');

                    return;
                }
                if ((string) ($order->payment_method ?? '') !== 'paypal') {
                    $validator->errors()->add('order_ids', 'All orders must use PayPal for this checkout.');

                    return;
                }
            }
        });
    }
}
