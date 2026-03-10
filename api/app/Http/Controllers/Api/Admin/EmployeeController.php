<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\EmployeeStoreRequest;
use App\Http\Requests\Admin\EmployeeUpdateRequest;
use App\Domain\Auth\Enums\UserRole;
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

        $employee = auth('employee')->user();
        if ($employee && UserRole::isWarehouseScoped($employee->role)) {
            $managedIds = $employee->getManagedWarehouseIds();
            if (empty($managedIds)) {
                $filters['warehouse_id'] = '__none__';
            } else {
                $filters['warehouse_ids'] = $managedIds;
            }
        }

        $perPage = min((int) $request->get('per_page', 15), 100);

        $employees = $this->employeeService->getAll($filters, $perPage);

        return $this->successResponse($employees);
    }

    public function store(EmployeeStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        if (($data['role'] ?? '') === UserRole::WarehouseManager->value && ! empty($data['warehouse_ids'] ?? [])) {
            $data['warehouse_id'] = $data['warehouse_ids'][0];
        }
        $currentEmployee = auth('employee')->user();
        if ($currentEmployee && UserRole::isWarehouseScoped($currentEmployee->role)) {
            $wid = $data['warehouse_id'] ?? null;
            $managedIds = $currentEmployee->getManagedWarehouseIds();
            if (empty($managedIds) || $wid === null || ! in_array((string) $wid, $managedIds, true)) {
                return $this->errorResponse('Forbidden. You can only add staff to one of your warehouses.', 403);
            }
            if (($data['role'] ?? '') !== UserRole::Shipping->value) {
                return $this->errorResponse('Forbidden. Warehouse managers can only add shipping staff to their warehouse.', 403);
            }
        }

        $employee = $this->employeeService->create($data);

        return $this->successResponse($employee->fresh(['warehouse']), 'Employee created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $employee = $this->employeeService->getById($id);

        if (! $employee) {
            return $this->errorResponse('Employee not found', 404);
        }

        $currentEmployee = auth('employee')->user();
        if ($currentEmployee && UserRole::isWarehouseScoped($currentEmployee->role)) {
            $managedIds = $currentEmployee->getManagedWarehouseIds();
            if (empty($managedIds) || ! in_array((string) $employee->warehouse_id, $managedIds, true)) {
                return $this->errorResponse('Forbidden. You can only access employees of your warehouses.', 403);
            }
        }

        return $this->successResponse($employee);
    }

    public function update(EmployeeUpdateRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['role']) && $data['role'] === UserRole::WarehouseManager->value && ! empty($data['warehouse_ids'] ?? [])) {
            $data['warehouse_id'] = $data['warehouse_ids'][0];
        }
        $currentEmployee = auth('employee')->user();
        if ($currentEmployee && UserRole::isWarehouseScoped($currentEmployee->role)) {
            $existing = $this->employeeService->getById($id);
            $managedIds = $currentEmployee->getManagedWarehouseIds();
            if (! $existing || empty($managedIds) || ! in_array((string) $existing->warehouse_id, $managedIds, true)) {
                return $this->errorResponse('Forbidden. You can only update employees of your warehouses.', 403);
            }
            if (isset($data['warehouse_id']) && ! in_array((string) $data['warehouse_id'], $managedIds, true)) {
                return $this->errorResponse('Forbidden. You cannot assign employees to a warehouse you do not manage.', 403);
            }
            if (isset($data['role']) && $data['role'] !== UserRole::Shipping->value) {
                return $this->errorResponse('Forbidden. Warehouse managers can only assign the shipping role to staff in their warehouse.', 403);
            }
        }

        if (empty($data['password'])) {
            unset($data['password'], $data['password_confirmation']);
        }

        $employee = $this->employeeService->update($id, $data);

        if (! $employee) {
            return $this->errorResponse('Employee not found', 404);
        }

        return $this->successResponse($employee, 'Employee updated');
    }
}
