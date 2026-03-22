<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\CustomerConvertToEmployeeRequest;
use App\Http\Requests\Admin\CustomerUpdateRequest;
use App\Infrastructure\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends BaseApiController
{
    public function __construct(
        protected CustomerService $customerService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'country' => $request->get('country'),
            'city' => $request->get('city'),
        ];
        $perPage = min((int) $request->get('per_page', 15), 100);

        $customers = $this->customerService->getAll($filters, $perPage);

        return $this->successResponse($customers);
    }

    public function show(string $id): JsonResponse
    {
        $customer = $this->customerService->getById($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found', 404);
        }

        return $this->successResponse($customer);
    }

    public function update(CustomerUpdateRequest $request, string $id): JsonResponse
    {
        $customer = $this->customerService->updateById($id, $request->validated());

        if (! $customer) {
            return $this->errorResponse('Customer not found', 404);
        }

        return $this->successResponse($customer, 'Customer updated');
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->customerService->deleteById($id);

        if (! $deleted) {
            return $this->errorResponse('Customer not found', 404);
        }

        return $this->successResponse(null, 'Customer deleted');
    }

    public function convertToEmployee(CustomerConvertToEmployeeRequest $request, string $id): JsonResponse
    {
        $customer = $this->customerService->getById($id);

        if (! $customer) {
            return $this->errorResponse('Customer not found', 404);
        }

        $employee = $this->customerService->convertToEmployee($customer, $request->validated());
        $this->customerService->deleteById($id);

        return $this->successResponse($employee, 'Customer converted to employee', 201);
    }
}
