<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;

class WarehouseStoreRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'country' => ['required', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'location' => ['nullable', 'array'],
            'location.type' => ['required_with:location', 'string', 'in:Point'],
            'location.coordinates' => ['required_with:location', 'array'],
            'location.coordinates.0' => ['required_with:location', 'numeric'],
            'location.coordinates.1' => ['required_with:location', 'numeric'],
        ];
    }
}
