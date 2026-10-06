<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\BaseFormRequest;
use App\Models\Setting;
use Illuminate\Validation\Validator;

class SubmitWarehouseOrderQuoteRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_fee' => ['required', 'numeric', 'min:0'],
            'shipping_method' => ['sometimes', 'nullable', 'string', 'max:500'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $method = $this->input('payment_method');
            if (! $method || ! is_string($method)) {
                return;
            }
            $enabled = Setting::enabledPaymentMethodIds();
            if ($enabled !== [] && ! in_array((string) $method, $enabled, true)) {
                $validator->errors()->add('payment_method', 'This payment method is not available or not enabled.');
            }
        });
    }
}
