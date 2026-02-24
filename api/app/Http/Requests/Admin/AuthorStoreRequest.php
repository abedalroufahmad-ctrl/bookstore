<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class AuthorStoreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'biography' => ['nullable', 'string'],
        ];
    }
}
