<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class CommissionSettlementStoreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/'],
            'note' => ['nullable', 'string', 'max:1000'],
            'reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
