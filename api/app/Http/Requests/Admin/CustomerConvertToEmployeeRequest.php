<?php

namespace App\Http\Requests\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Requests\BaseFormRequest;
use App\Models\Employee;
use App\Models\Warehouse;
use Illuminate\Validation\Rule;

class CustomerConvertToEmployeeRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => [
                'required',
                'string',
                Rule::in([
                    UserRole::Manager->value,
                    UserRole::Shipping->value,
                    UserRole::Review->value,
                    UserRole::Accounting->value,
                    UserRole::WarehouseManager->value,
                ]),
            ],
            'warehouse_id' => ['required', 'string'],
            // Omit to keep the same password as the customer account.
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $warehouseId = (string) $this->input('warehouse_id', '');
            if ($warehouseId === '' || ! Warehouse::query()->whereKey($warehouseId)->exists()) {
                $validator->errors()->add('warehouse_id', 'The selected warehouse does not exist.');
            }

            $customer = $this->route('id')
                ? \App\Models\Customer::query()->find((string) $this->route('id'))
                : null;
            if ($customer && Employee::query()->where('email', $customer->email)->exists()) {
                $validator->errors()->add('email', 'An employee with this email already exists.');
            }
        });
    }
}

