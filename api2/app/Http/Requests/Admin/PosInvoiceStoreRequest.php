<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class PosInvoiceStoreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.book_id' => ['required', 'string', 'max:64'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'warehouse_id' => ['required', 'string', 'max:64'],
            'customer_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
