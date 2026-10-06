<?php

namespace App\Http\Requests\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;

class PublisherSettingsUpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'support_email' => ['nullable', 'email'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'return_policy' => ['nullable', 'string', 'max:5000'],
            'default_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', 'max:50'],
            'paypal_email' => ['nullable', 'email', 'max:255'],
            'paypal_merchant_id' => ['nullable', 'string', 'max:64'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:128'],
        ];

        if (auth('employee')->user()?->role === UserRole::Manager->value) {
            $rules['platform_commission_percent'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        return $rules;
    }
}
