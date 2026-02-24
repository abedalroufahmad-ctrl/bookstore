<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\BaseFormRequest;

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
            'payment_info' => ['nullable', 'array'],
        ];
    }
}
