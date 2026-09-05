<?php

namespace App\Http\Requests\Admin;

use App\Domain\Book\Enums\BookCondition;
use App\Http\Requests\BaseFormRequest;
use App\Models\Book;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'isbn' => ['required', 'string', 'max:20'],
            'publish_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
            'edition_number' => ['nullable', 'integer', 'min:1'],
            'binding_type' => ['nullable', 'string', 'max:50'],
            'paper_type' => ['nullable', 'string', 'max:50'],
            'publisher_id' => ['nullable', 'string'],
            'publisher_ids' => ['nullable', 'array'],
            'publisher_ids.*' => ['required', 'string'],
            'warehouse_id' => ['nullable', 'string'],
            'warehouse_ids' => ['required', 'array', 'min:1'],
            'warehouse_ids.*' => ['required', 'string'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'condition' => ['nullable', Rule::in(BookCondition::values())],
            'is_visible' => ['nullable', 'boolean'],
            'is_sold' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $isbn = (string) $this->input('isbn', '');
            $warehouseIds = array_values(array_unique(array_filter(array_map('strval', (array) $this->input('warehouse_ids', [])))));
            if ($isbn === '' || $warehouseIds === []) {
                return;
            }

            foreach ($warehouseIds as $warehouseId) {
                if (Book::where('isbn', $isbn)->where('warehouse_id', $warehouseId)->exists()) {
                    $validator->errors()->add(
                        'warehouse_ids',
                        "ISBN {$isbn} already exists in one of the selected warehouses."
                    );
                    break;
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $warehouseIds = $this->input('warehouse_ids');
        if (! is_array($warehouseIds) || $warehouseIds === []) {
            $single = $this->input('warehouse_id');
            if (is_string($single) && $single !== '') {
                $warehouseIds = [$single];
            } else {
                $warehouseIds = [];
            }
        } else {
            $warehouseIds = array_values(array_unique(array_filter(array_map('strval', $warehouseIds))));
        }

        $this->merge([
            'warehouse_ids' => $warehouseIds,
            'warehouse_id' => $warehouseIds[0] ?? $this->input('warehouse_id'),
        ]);

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
