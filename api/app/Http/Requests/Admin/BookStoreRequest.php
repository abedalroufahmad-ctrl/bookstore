<?php

namespace App\Http\Requests\Admin;

use App\Domain\Book\Enums\BookCondition;
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
        $warehouseId = (string) $this->input('warehouse_id', '');

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
            'isbn' => [
                'required',
                'string',
                'max:20',
                Rule::unique('books', 'isbn')->where(fn ($q) => $q->where('warehouse_id', $warehouseId)),
            ],
            'publish_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'edition_number' => ['nullable', 'integer', 'min:1'],
            'binding_type' => ['nullable', 'string', 'max:50'],
            'paper_type' => ['nullable', 'string', 'max:50'],
            'publisher_id' => ['nullable', 'string'],
            'warehouse_id' => ['required', 'string'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'condition' => ['nullable', Rule::in(BookCondition::values())],
            'is_visible' => ['nullable', 'boolean'],
            'is_sold' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('condition')) {
            $this->merge([
                'condition' => BookCondition::normalize($this->input('condition'))->value,
            ]);
        }

        if ($this->input('condition') === BookCondition::Used->value && ! $this->filled('stock_quantity')) {
            $this->merge(['stock_quantity' => 1]);
        }

        if ($this->input('condition') === BookCondition::Used->value && (int) $this->input('stock_quantity', 0) > 1) {
            $this->merge(['stock_quantity' => 1]);
        }
    }
}
