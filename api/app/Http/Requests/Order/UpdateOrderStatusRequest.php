<?php

namespace App\Http\Requests\Order;

use App\Http\Requests\BaseFormRequest;
use App\Services\OrderService;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(OrderService::VALID_STATUSES)],
        ];
    }
}
