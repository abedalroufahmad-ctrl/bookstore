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
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', 'max:50'],
        ];

        if (auth('employee')->user()?->role === UserRole::Manager->value) {
            $rules['platform_commission_percent'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        if (auth('employee')->user()?->role === UserRole::PublisherManager->value) {
            $rules['default_discount'] = ['nullable', 'numeric', 'min:0', 'max:100'];
            $rules['paypal_email'] = ['nullable', 'email', 'max:255'];
            $rules['paypal_merchant_id'] = ['nullable', 'string', 'max:64'];
            $rules['bank_name'] = ['nullable', 'string', 'max:255'];
            $rules['bank_account_number'] = ['nullable', 'string', 'max:128'];
        }

        return $rules;
    }
}
