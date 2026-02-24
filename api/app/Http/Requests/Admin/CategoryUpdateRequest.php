<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CategoryUpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('id');

        return [
            'dewey_code' => ['sometimes', 'string', 'max:50', Rule::unique('categories', 'dewey_code')->ignore($categoryId, '_id')],
            'subject_title' => ['sometimes', 'string', 'max:255'],
            'subject_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
