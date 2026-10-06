<?php

namespace App\Http\Requests\Admin;

use App\Domain\Employee\Interfaces\EmployeeRepositoryInterface;
use App\Http\Requests\BaseFormRequest;

class AssignOrderRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'string', function ($attr, $value, $fail) {
                if (! app(EmployeeRepositoryInterface::class)->exists($value)) {
                    $fail('The selected employee does not exist.');
                }
            }],
        ];
    }
}
