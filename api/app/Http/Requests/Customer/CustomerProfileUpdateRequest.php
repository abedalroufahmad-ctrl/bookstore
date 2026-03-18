<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CustomerProfileUpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customer = auth('customer')->user();
        $customerId = $customer ? $customer->getKey() : null;

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customerId, '_id')],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            'city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'payment_info' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
