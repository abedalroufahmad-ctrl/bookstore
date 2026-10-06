<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class CategoryStoreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dewey_code' => ['required', 'string', 'max:50', 'unique:categories,dewey_code'],
            'subject_title_en' => ['required', 'string', 'max:255'],
            'subject_title_ar' => ['nullable', 'string', 'max:255'],
            'subject_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
