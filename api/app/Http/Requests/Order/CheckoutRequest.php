<?php

namespace App\Http\Requests\Order;

use App\Models\Setting;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Validator;

class CheckoutRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_address' => ['required', 'array'],
            'shipping_address.address' => ['required', 'string', 'max:500'],
            'shipping_address.city' => ['required', 'string', 'max:100'],
            'shipping_address.country' => ['required', 'string', 'max:100'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_info' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $method = $this->input('payment_method');
            if (! $method) {
                return;
            }
            $list = Setting::get('payment_methods');
            if (! is_array($list)) {
                $validator->errors()->add('payment_method', 'No payment methods configured.');
                return;
            }
            $enabledIds = [];
            foreach ($list as $item) {
                if (is_array($item) && ! empty($item['id']) && ! empty($item['enabled'])) {
                    $enabledIds[] = (string) $item['id'];
                }
            }
            if (! in_array((string) $method, $enabledIds, true)) {
                $validator->errors()->add('payment_method', 'This payment method is not available or not enabled.');
            }
        });
    }
}
