<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\BaseFormRequest;
use App\Models\Setting;
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
            // Non-sensitive payment metadata only — never accept PAN/CVV/full card data.
            'payment_info' => ['nullable', 'array'],
            'payment_info.provider' => ['sometimes', 'nullable', 'string', 'max:50'],
            'payment_info.token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_info.last4' => ['sometimes', 'nullable', 'string', 'size:4'],
            'payment_info.brand' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $info = $this->input('payment_info');
        if (! is_array($info)) {
            return;
        }
        $this->merge([
            'payment_info' => array_intersect_key($info, array_flip(['provider', 'token', 'last4', 'brand'])),
        ]);
    }


    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $method = $this->input('payment_method');
            if (! $method) {
                return;
            }
            $enabledIds = Setting::enabledPaymentMethodIds();
            if ($enabledIds === []) {
                $validator->errors()->add('payment_method', 'No payment methods configured.');

                return;
            }
            if (! in_array((string) $method, $enabledIds, true)) {
                $validator->errors()->add('payment_method', 'This payment method is not available or not enabled.');
            }
        });
    }
}
