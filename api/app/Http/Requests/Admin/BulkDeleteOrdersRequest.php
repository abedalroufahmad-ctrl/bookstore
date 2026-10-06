<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class BulkDeleteOrdersRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'string'],
        ];
    }
}
