<?php

namespace App\Http\Requests\Cart;

use App\Http\Requests\BaseFormRequest;

class UpdateCartItemRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:0'],
        ];
    }
}
