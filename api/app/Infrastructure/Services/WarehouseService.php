<?php

namespace App\Infrastructure\Services;

use App\Domain\Auth\Enums\UserRole;
use App\Domain\Warehouse\Interfaces\WarehouseRepositoryInterface;
use App\Models\Employee;
use App\Models\Warehouse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class WarehouseService
{
    public function __construct(
        protected WarehouseRepositoryInterface $repository
    ) {}

    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getPaginated($filters, $perPage);
    }

    public function getById(string $id, array $with = ['employees', 'books', 'manager', 'publisher']): ?Warehouse
    {
        return $this->repository->findById($id, $with);
    }

    public function create(array $data): Warehouse
    {
        $managerId = $data['manager_id'] ?? null;
        $employeeIds = $data['employee_ids'] ?? null;
        // Keep manager_id on the warehouse document; only strip the helper employee_ids array
        unset($data['employee_ids']);
        $warehouse = $this->repository->create($data);
        if ($managerId) {
            $this->syncManagerToWarehouse($managerId, $warehouse->getKey());
        }
        if (is_array($employeeIds) && ! empty(array_filter($employeeIds, fn ($v) => $v !== '' && $v !== null))) {
            $this->assignEmployeesToWarehouse($warehouse->getKey(), $employeeIds, false, null);
        }

        return $warehouse->fresh(['employees', 'books', 'manager', 'publisher']);
    }

    public function update(string $id, array $data, $currentEmployee = null): ?Warehouse
    {
        $managerId = array_key_exists('manager_id', $data) ? $data['manager_id'] : null;
        $employeeIds = $data['employee_ids'] ?? null;
        // Keep manager_id so the warehouse document stores the selected manager;
        // only remove the helper employee_ids array.
        unset($data['employee_ids']);
        $updated = $this->repository->update($id, $data);
        if ($updated && $managerId) {
            $this->syncManagerToWarehouse($managerId, $id);
        }
        if ($updated && is_array($employeeIds)) {
            // Never silently rewrite roles on assign — only link warehouse membership.
            $this->assignEmployeesToWarehouse($id, $employeeIds, false, $currentEmployee);
        }

        return $updated ? $this->repository->findById($id, ['employees', 'books', 'manager', 'publisher']) : null;
    }

    /**
     * Set warehouse membership for employees. Does not change role.
     * Warehouse managers may only assign shipping/accounting staff (never managers / other WMs).
     */
    private function assignEmployeesToWarehouse(
        string $warehouseId,
        array $employeeIds,
        bool $setRoleShipping = false,
        $currentEmployee = null
    ): void {
        $ids = array_values(array_filter($employeeIds, fn ($v) => $v !== '' && $v !== null));
        if (empty($ids)) {
            return;
        }

        $query = Employee::whereIn('_id', $ids);
        if ($currentEmployee && $currentEmployee->role === UserRole::WarehouseManager->value) {
            $query->whereIn('role', UserRole::warehouseManagerStaffRoles());
        }

        $update = ['warehouse_id' => $warehouseId];
        if ($setRoleShipping) {
            $update['role'] = UserRole::Shipping->value;
        }
        $query->update($update);
    }

    /**
     * Ensure the assigned manager employee has this warehouse in warehouse_ids.
     * Only sets role to warehouse_manager when the employee is not already a global manager.
     */
    private function syncManagerToWarehouse(string $employeeId, string $warehouseId): void
    {
        $employee = Employee::find($employeeId);
        if (! $employee) {
            return;
        }
        // Never demote a global manager via warehouse assignment.
        if ($employee->role === UserRole::Manager->value) {
            return;
        }
        $ids = $employee->warehouse_ids ?? [];
        if (! is_array($ids)) {
            $ids = [];
        }
        $wid = (string) $warehouseId;
        if (! in_array($wid, array_map('strval', $ids), true)) {
            $ids[] = $warehouseId;
        }
        $payload = [
            'warehouse_id' => $ids[0],
            'warehouse_ids' => $ids,
        ];
        if ($employee->role !== UserRole::WarehouseManager->value) {
            $payload['role'] = UserRole::WarehouseManager->value;
        }
        $employee->update($payload);
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
