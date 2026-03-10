<?php

namespace App\Http\Requests\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class EmployeeStoreRequest extends BaseFormRequest
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

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:employees,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in($employeeRoles)],
            'warehouse_id' => ['required_unless:role,' . UserRole::WarehouseManager->value, 'nullable', 'string'],
            'warehouse_ids' => ['required_if:role,' . UserRole::WarehouseManager->value, 'nullable', 'array', 'min:1'],
            'warehouse_ids.*' => ['string'],
        ];
    }
}
