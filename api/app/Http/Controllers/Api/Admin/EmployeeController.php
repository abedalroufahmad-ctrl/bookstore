<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\EmployeeStoreRequest;
use App\Infrastructure\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends BaseApiController
{
    public function __construct(
        protected EmployeeService $employeeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'search' => $request->get('search'),
            'role' => $request->get('role'),
            'warehouse_id' => $request->get('warehouse_id'),
        ];
        $perPage = min((int) $request->get('per_page', 15), 100);

        $employees = $this->employeeService->getAll($filters, $perPage);

        return $this->successResponse($employees);
    }

    public function store(EmployeeStoreRequest $request): JsonResponse
    {
        $employee = $this->employeeService->create($request->validated());

        return $this->successResponse($employee->fresh(['warehouse']), 'Employee created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $employee = $this->employeeService->getById($id);

        if (! $employee) {
            return $this->errorResponse('Employee not found', 404);
        }

        return $this->successResponse($employee);
    }
}
