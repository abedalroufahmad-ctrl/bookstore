<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
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
}
