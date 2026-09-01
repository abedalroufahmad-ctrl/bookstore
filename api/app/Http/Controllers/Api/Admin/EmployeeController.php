<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Auth\Enums\UserRole;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\EmployeeStoreRequest;
use App\Http\Requests\Admin\EmployeeUpdateRequest;
use App\Infrastructure\Services\EmployeeService;
use App\Models\Employee;
use App\Models\Warehouse;
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

        $currentEmployee = auth('employee')->user();
        if ($currentEmployee && UserRole::isLimitedToAssignedWarehouses($currentEmployee->role)) {
            $managedIds = $currentEmployee->getManagedWarehouseIds();
            if (! empty($managedIds)) {
                $filters['warehouse_ids'] = $managedIds;
            } elseif (! empty($currentEmployee->warehouse_id)) {
                $filters['warehouse_id'] = $currentEmployee->warehouse_id;
            } else {
                $filters['warehouse_id'] = '__none__';
            }
        }

        if ($currentEmployee && UserRole::isPublisherScoped($currentEmployee->role)) {
            $publisherId = $currentEmployee->getManagedPublisherId();
            if ($publisherId === null) {
                $filters['warehouse_id'] = '__none__';
            } else {
                $filters['linked_publisher_id'] = $publisherId;
                $filters['publisher_warehouse_ids'] = $this->warehouseIdsForPublisher($publisherId);
            }
        }

        $perPage = min((int) $request->get('per_page', 15), 100);

        $employees = $this->employeeService->getAll($filters, $perPage);

        return $this->successResponse($employees);
    }

    public function store(EmployeeStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        if ((($data['role'] ?? '') === UserRole::WarehouseManager->value || ($data['role'] ?? '') === UserRole::Shipping->value || ($data['role'] ?? '') === UserRole::DirectSales->value) && ! empty($data['warehouse_ids'] ?? [])) {
            $data['warehouse_id'] = $data['warehouse_ids'][0];
        }
        $currentEmployee = auth('employee')->user();
        if ($currentEmployee && UserRole::isLimitedToAssignedWarehouses($currentEmployee->role) && ! UserRole::isWarehouseScoped($currentEmployee->role)) {
            return $this->errorResponse('Forbidden. Only managers or warehouse managers can create employees.', 403);
        }
        if ($currentEmployee && UserRole::isWarehouseScoped($currentEmployee->role)) {
            $wid = $data['warehouse_id'] ?? null;
            $managedIds = $currentEmployee->getManagedWarehouseIds();
            if (empty($managedIds) || $wid === null || ! in_array((string) $wid, $managedIds, true)) {
                return $this->errorResponse('Forbidden. You can only add staff to one of your warehouses.', 403);
            }
            $role = (string) ($data['role'] ?? '');
            if (! in_array($role, UserRole::warehouseManagerStaffRoles(), true)) {
                return $this->errorResponse('Forbidden. Warehouse managers can only add shipping, accounting, or direct sales staff to their warehouse.', 403);
            }
        }

        if ($currentEmployee && UserRole::isPublisherScoped($currentEmployee->role)) {
            $forbidden = $this->assertPublisherManagerMayWrite($currentEmployee, $data, null);
            if ($forbidden !== null) {
                return $forbidden;
            }
        }

        $employee = $this->employeeService->create($data);

        return $this->successResponse($employee->fresh(['warehouse.publisher', 'publisher']), 'Employee created', 201);
    }

    public function show(string $id): JsonResponse
    {
        $employee = $this->employeeService->getById($id);

        if (! $employee) {
            return $this->errorResponse('Employee not found', 404);
        }

        $currentEmployee = auth('employee')->user();
        if ($currentEmployee && UserRole::isLimitedToAssignedWarehouses($currentEmployee->role)) {
            $wid = (string) ($employee->warehouse_id ?? '');
            if ($wid === '' || ! $currentEmployee->managesWarehouse($wid)) {
                return $this->errorResponse('Forbidden. You can only view employees in your warehouse(s).', 403);
            }
        }

        if ($currentEmployee && UserRole::isPublisherScoped($currentEmployee->role)) {
            if (! $this->publisherManagerCanAccess($currentEmployee, $employee)) {
                return $this->errorResponse('Forbidden. You can only view employees linked to your publisher.', 403);
            }
        }

        return $this->successResponse($employee);
    }

    public function update(EmployeeUpdateRequest $request, string $id): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['role']) && ($data['role'] === UserRole::WarehouseManager->value || $data['role'] === UserRole::Shipping->value || $data['role'] === UserRole::DirectSales->value) && ! empty($data['warehouse_ids'] ?? [])) {
            $data['warehouse_id'] = $data['warehouse_ids'][0];
        }
        $currentEmployee = auth('employee')->user();
        if ($currentEmployee && UserRole::isLimitedToAssignedWarehouses($currentEmployee->role) && ! UserRole::isWarehouseScoped($currentEmployee->role)) {
            return $this->errorResponse('Forbidden. Only managers or warehouse managers can update employees.', 403);
        }
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

        if ($currentEmployee && UserRole::isPublisherScoped($currentEmployee->role)) {
            $existing = $this->employeeService->getById($id);
            if (! $existing || ! $this->publisherManagerCanAccess($currentEmployee, $existing)) {
                return $this->errorResponse('Forbidden. You can only update employees linked to your publisher.', 403);
            }
            if ($existing->role === UserRole::Manager->value) {
                return $this->errorResponse('Forbidden. You cannot update global managers.', 403);
            }
            $forbidden = $this->assertPublisherManagerMayWrite($currentEmployee, $data, $existing);
            if ($forbidden !== null) {
                return $forbidden;
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

    public function destroy(string $id): JsonResponse
    {
        $existing = $this->employeeService->getById($id);
        if (! $existing) {
            return $this->errorResponse('Employee not found', 404);
        }

        $currentEmployee = auth('employee')->user();
        if (! $currentEmployee) {
            return $this->errorResponse('Unauthenticated.', 401);
        }

        if ((string) $currentEmployee->getKey() === (string) $existing->getKey()) {
            return $this->errorResponse('Forbidden. You cannot delete your own account.', 403);
        }

        if (UserRole::isWarehouseScoped($currentEmployee->role)) {
            $wid = (string) ($existing->warehouse_id ?? '');
            if ($wid === '' || ! $currentEmployee->managesWarehouse($wid)) {
                return $this->errorResponse('Forbidden. You can only delete staff in your warehouse(s).', 403);
            }
            if (! in_array((string) $existing->role, UserRole::warehouseManagerStaffRoles(), true)) {
                return $this->errorResponse('Forbidden. Warehouse managers can only delete shipping, accounting, or direct sales staff.', 403);
            }
        }

        if (UserRole::isPublisherScoped($currentEmployee->role)) {
            if (! $this->publisherManagerCanAccess($currentEmployee, $existing)) {
                return $this->errorResponse('Forbidden. You can only delete employees linked to your publisher.', 403);
            }
            if ($existing->role === UserRole::Manager->value) {
                return $this->errorResponse('Forbidden. You cannot delete global managers.', 403);
            }
            if (! in_array((string) $existing->role, UserRole::publisherManagerStaffRoles(), true)) {
                return $this->errorResponse('Forbidden. You cannot delete this employee role.', 403);
            }
        }

        if (! $this->employeeService->delete($id)) {
            return $this->errorResponse('Employee not found', 404);
        }

        return $this->successResponse(null, 'Employee deleted');
    }

    /**
     * @return list<string>
     */
    private function warehouseIdsForPublisher(string $publisherId): array
    {
        return Warehouse::query()
            ->where('publisher_id', $publisherId)
            ->get(['_id'])
            ->map(fn (Warehouse $w) => (string) $w->getKey())
            ->values()
            ->all();
    }

    private function publisherManagerCanAccess(Employee $current, Employee $target): bool
    {
        $publisherId = $current->getManagedPublisherId();
        if ($publisherId === null) {
            return false;
        }

        return $current->employeeBelongsToPublisher(
            $target,
            $this->warehouseIdsForPublisher($publisherId)
        );
    }

    /**
     * Enforce role + warehouse/publisher scope for publisher_manager create/update.
     *
     * @param  array<string, mixed>  $data
     */
    private function assertPublisherManagerMayWrite(Employee $current, array &$data, ?Employee $existing): ?JsonResponse
    {
        $publisherId = $current->getManagedPublisherId();
        if ($publisherId === null) {
            return $this->errorResponse('Forbidden. Publisher manager has no publisher assigned.', 403);
        }

        $warehouseIds = $this->warehouseIdsForPublisher($publisherId);
        $role = array_key_exists('role', $data)
            ? (string) $data['role']
            : (string) ($existing?->role ?? '');

        if ($role === '' || ! in_array($role, UserRole::publisherManagerStaffRoles(), true)) {
            return $this->errorResponse(
                'Forbidden. Publisher managers can only assign shipping, review, accounting, warehouse manager, publisher manager, or direct sales roles.',
                403
            );
        }

        if ($role === UserRole::PublisherManager->value) {
            $requested = (string) ($data['publisher_id'] ?? $existing?->publisher_id ?? $publisherId);
            if ($requested === '') {
                return $this->errorResponse('Forbidden. Publisher is required.', 403);
            }
            $data['publisher_id'] = $requested;
            unset($data['warehouse_id'], $data['warehouse_ids']);

            return null;
        }

        // Warehouse-based staff stay on this publisher manager's publisher.
        $data['publisher_id'] = $publisherId;

        if ($role === UserRole::WarehouseManager->value || $role === UserRole::Shipping->value || $role === UserRole::DirectSales->value) {
            $ids = array_values(array_map('strval', $data['warehouse_ids'] ?? ($existing?->warehouse_ids ?? [])));
            if ($ids === []) {
                return $this->errorResponse('Forbidden. Select at least one of your publisher\'s warehouses.', 403);
            }
            foreach ($ids as $wid) {
                if (! in_array($wid, $warehouseIds, true)) {
                    return $this->errorResponse('Forbidden. You can only assign warehouses belonging to your publisher.', 403);
                }
            }
            $data['warehouse_ids'] = $ids;
            $data['warehouse_id'] = $ids[0];

            return null;
        }

        $wid = (string) ($data['warehouse_id'] ?? $existing?->warehouse_id ?? '');
        if ($wid === '' || ! in_array($wid, $warehouseIds, true)) {
            return $this->errorResponse('Forbidden. You can only assign staff to warehouses belonging to your publisher.', 403);
        }
        $data['warehouse_id'] = $wid;
        unset($data['warehouse_ids']);

        return null;
    }
}
