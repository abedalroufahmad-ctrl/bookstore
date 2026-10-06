<?php

namespace App\Http\Requests\Cart;

use App\Http\Requests\BaseFormRequest;

class AddToCartRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'string'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:999'],
        ];
    }
}
