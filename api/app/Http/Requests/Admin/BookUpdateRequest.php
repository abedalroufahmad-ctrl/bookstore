<?php

namespace App\Http\Requests\Admin;

use App\Domain\Book\Enums\BookCondition;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class BookUpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bookId = $this->route('id');
        $warehouseId = (string) $this->input('warehouse_id', '');

        return [
            'title' => ['sometimes', 'string', 'max:500'],
            'author_ids' => ['sometimes', 'array'],
            'author_ids.*' => ['required', 'string'],
            'category_id' => ['sometimes', 'string'],
            'size' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'cover_image' => ['nullable', 'string', 'max:500'],
            'cover_image_thumb' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'pages' => ['nullable', 'integer', 'min:1'],
            'isbn' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('books', 'isbn')
                    ->ignore($bookId, '_id')
                    ->where(fn ($q) => $q->where('warehouse_id', $warehouseId !== '' ? $warehouseId : $this->existingWarehouseId())),
            ],
            'publish_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'edition_number' => ['nullable', 'integer', 'min:1'],
            'binding_type' => ['nullable', 'string', 'max:50'],
            'paper_type' => ['nullable', 'string', 'max:50'],
            'publisher_id' => ['nullable', 'string'],
            'publisher_ids' => ['nullable', 'array'],
            'publisher_ids.*' => ['required', 'string'],
            'warehouse_id' => ['sometimes', 'string'],
            'warehouse_ids' => ['sometimes', 'array', 'min:1'],
            'warehouse_ids.*' => ['required', 'string'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'condition' => ['nullable', Rule::in(BookCondition::values())],
            'is_visible' => ['nullable', 'boolean'],
            'is_sold' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('warehouse_ids') && is_array($this->input('warehouse_ids'))) {
            $warehouseIds = array_values(array_unique(array_filter(array_map('strval', $this->input('warehouse_ids')))));
            $this->merge([
                'warehouse_ids' => $warehouseIds,
                'warehouse_id' => $this->input('warehouse_id') ?: ($warehouseIds[0] ?? null),
            ]);
        }

        if ($this->has('condition')) {
            $this->merge([
                'condition' => BookCondition::normalize($this->input('condition'))->value,
            ]);
        }

        if ($this->input('condition') === BookCondition::Used->value && $this->has('stock_quantity') && (int) $this->input('stock_quantity') > 1) {
            $this->merge(['stock_quantity' => 1]);
        }
    }

    private function existingWarehouseId(): string
    {
        $bookId = $this->route('id');
        if (! $bookId) {
            return '';
        }

        $book = \App\Models\Book::find($bookId);

        return $book ? (string) $book->warehouse_id : '';
    }
}
