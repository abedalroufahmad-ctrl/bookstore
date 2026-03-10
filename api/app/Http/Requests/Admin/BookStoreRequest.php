<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class BookStoreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:500'],
            'author_ids' => ['required', 'array'],
            'author_ids.*' => ['required', 'string'],
            'category_id' => ['required', 'string'],
            'size' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'cover_image' => ['nullable', 'string', 'max:500'],
            'cover_image_thumb' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'pages' => ['nullable', 'integer', 'min:1'],
            'isbn' => ['required', 'string', 'max:20', 'unique:books,isbn'],
            'publish_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'edition_number' => ['nullable', 'integer', 'min:1'],
            'binding_type' => ['nullable', 'string', 'max:50'],
            'paper_type' => ['nullable', 'string', 'max:50'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'warehouse_id' => ['required', 'string'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
