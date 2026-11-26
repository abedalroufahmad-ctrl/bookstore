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
            'currency_symbol' => ['nullable', 'string', 'max:8'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', 'max:50'],
        ];

        if (auth('employee')->user()?->role === UserRole::Manager->value) {
            $rules['platform_commission_percent'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        if (auth('employee')->user()?->role === UserRole::PublisherManager->value) {
            $rules['default_discount'] = ['nullable', 'numeric', 'min:0', 'max:100'];
            $rules['currencies'] = ['sometimes', 'array', 'max:20'];
            $rules['currencies.*.code'] = ['required', 'string', 'regex:/^[A-Z]{3}$/'];
            $rules['currencies.*.name'] = ['required', 'string', 'max:100'];
            $rules['currencies.*.symbol'] = ['required', 'string', 'max:12'];
            $rules['default_currency_code'] = ['nullable', 'string', 'regex:/^[A-Z]{3}$/'];
            $rules['paypal_email'] = ['nullable', 'email', 'max:255'];
            $rules['paypal_merchant_id'] = ['nullable', 'string', 'max:64'];
            $rules['bank_name'] = ['nullable', 'string', 'max:255'];
            $rules['bank_account_number'] = ['nullable', 'string', 'max:128'];
        }

        return $rules;
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $currencies = $this->input('currencies');
            if (! is_array($currencies)) {
                return;
            }

            $codes = array_map(
                static fn ($currency) => is_array($currency) ? ($currency['code'] ?? null) : null,
                $currencies
            );
            if (count($codes) !== count(array_unique($codes))) {
                $validator->errors()->add('currencies', 'Currency codes must be unique.');
            }

            $default = $this->input('default_currency_code');
            if (is_string($default) && $default !== '' && ! in_array($default, $codes, true)) {
                $validator->errors()->add('default_currency_code', 'The default currency must be enabled.');
            }
        });
    }
}
