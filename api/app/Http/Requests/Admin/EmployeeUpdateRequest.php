<?php

namespace App\Http\Requests\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class EmployeeUpdateRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeRoles = [
            UserRole::Manager->value,
            UserRole::Shipping->value,
            UserRole::Review->value,
            UserRole::Accounting->value,
            UserRole::WarehouseManager->value,
        ];

        $id = $this->route('id');

        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('employees', 'email')->ignore($id, '_id')],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['sometimes', 'string', Rule::in($employeeRoles)],
            'warehouse_id' => ['sometimes', 'nullable', 'string'],
            'warehouse_ids' => ['sometimes', 'nullable', 'array', 'min:1'],
            'warehouse_ids.*' => ['string'],
        ];

        return $rules;
    }
}
