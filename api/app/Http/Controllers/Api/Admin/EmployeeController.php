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

        // Warehouse managers need to see all employees so they can pick who to assign to their warehouse.

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
            $role = (string) ($data['role'] ?? '');
            if (! in_array($role, UserRole::warehouseManagerStaffRoles(), true)) {
                return $this->errorResponse('Forbidden. Warehouse managers can only add shipping or accounting staff to their warehouse.', 403);
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
            if (! $existing || empty($managedIds)) {
                return $this->errorResponse('Forbidden.', 403);
            }

            $existingWid = (string) ($existing->warehouse_id ?? '');
            $inMyWarehouse = $existingWid !== '' && in_array($existingWid, $managedIds, true);

            if ($inMyWarehouse) {
                if (isset($data['warehouse_id']) && ! in_array((string) $data['warehouse_id'], $managedIds, true)) {
                    return $this->errorResponse('Forbidden. You cannot assign employees to a warehouse you do not manage.', 403);
                }
                if (isset($data['role']) && ! in_array((string) $data['role'], UserRole::warehouseManagerStaffRoles(), true)) {
                    return $this->errorResponse('Forbidden. Warehouse managers can only assign shipping or accounting roles to staff in their warehouse.', 403);
                }
            } else {
                // Employee is not in one of this manager's warehouses: allow assigning them into a managed warehouse only.
                if ($existing->role === UserRole::WarehouseManager->value) {
                    return $this->errorResponse('Forbidden. You cannot reassign warehouse managers.', 403);
                }
                if (empty($data['warehouse_id']) || ! in_array((string) $data['warehouse_id'], $managedIds, true)) {
                    return $this->errorResponse('Forbidden. Set warehouse to one you manage to assign this employee.', 403);
                }
                $finalRole = array_key_exists('role', $data) ? (string) $data['role'] : (string) $existing->role;
                if (! in_array($finalRole, UserRole::warehouseManagerStaffRoles(), true)) {
                    return $this->errorResponse('Forbidden. Role must be shipping or accounting when assigning to your warehouse.', 403);
                }
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
