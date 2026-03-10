<?php

namespace App\Http\Requests\Admin;

use App\Domain\Employee\Interfaces\EmployeeRepositoryInterface;
use App\Http\Requests\BaseFormRequest;

class WarehouseUpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'manager_id' => [
                'nullable',
                'string',
                function ($attr, $value, $fail) {
                    if ($value !== '' && $value !== null && ! app(EmployeeRepositoryInterface::class)->exists($value)) {
                        $fail('The selected manager (employee) does not exist.');
                    }
                },
            ],
            'address' => ['sometimes', 'string', 'max:500'],
            'country' => ['sometimes', 'string', 'max:100'],
            'city' => ['sometimes', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['sometimes', 'string', 'email', 'max:255'],
            'location' => ['nullable', 'array'],
            'location.type' => ['required_with:location', 'string', 'in:Point'],
            'location.coordinates' => ['required_with:location', 'array'],
            'location.coordinates.0' => ['required_with:location', 'numeric'],
            'location.coordinates.1' => ['required_with:location', 'numeric'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string', 'max:100'],
            'shipping_options' => ['nullable', 'array'],
            'shipping_options.*' => ['string', 'max:255'],
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['string', function ($attr, $value, $fail) {
                if ($value !== '' && $value !== null && ! app(EmployeeRepositoryInterface::class)->exists($value)) {
                    $fail('One or more selected employees do not exist.');
                }
            }],
        ];
    }
}
