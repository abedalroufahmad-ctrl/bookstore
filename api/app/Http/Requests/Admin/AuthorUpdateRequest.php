<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class AuthorUpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'biography' => ['nullable', 'string'],
        ];
    }
}
