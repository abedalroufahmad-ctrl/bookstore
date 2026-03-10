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

    public function getById(string $id, array $with = ['employees', 'books', 'manager']): ?Warehouse
    {
        return $this->repository->findById($id, $with);
    }

    public function create(array $data): Warehouse
    {
        $managerId = $data['manager_id'] ?? null;
        $employeeIds = $data['employee_ids'] ?? null;
        unset($data['manager_id'], $data['employee_ids']);
        $warehouse = $this->repository->create($data);
        if ($managerId) {
            $this->syncManagerToWarehouse($managerId, $warehouse->getKey());
        }
        if (is_array($employeeIds) && ! empty(array_filter($employeeIds, fn ($v) => $v !== '' && $v !== null))) {
            $this->assignEmployeesToWarehouse($warehouse->getKey(), $employeeIds);
        }
        return $warehouse->fresh(['employees', 'books', 'manager']);
    }

    public function update(string $id, array $data, $currentEmployee = null): ?Warehouse
    {
        $managerId = array_key_exists('manager_id', $data) ? $data['manager_id'] : null;
        $employeeIds = $data['employee_ids'] ?? null;
        unset($data['manager_id'], $data['employee_ids']);
        $updated = $this->repository->update($id, $data);
        if ($updated && $managerId) {
            $this->syncManagerToWarehouse($managerId, $id);
        }
        if ($updated && is_array($employeeIds)) {
            $setRoleShipping = $currentEmployee && $currentEmployee->role === \App\Domain\Auth\Enums\UserRole::WarehouseManager->value;
            $this->assignEmployeesToWarehouse($id, $employeeIds, $setRoleShipping);
        }
        return $updated ? $this->repository->findById($id, ['employees', 'books', 'manager']) : null;
    }

    /**
     * Set warehouse_id for the given employees to this warehouse. Optionally set role to shipping (when assigned by WM).
     */
    private function assignEmployeesToWarehouse(string $warehouseId, array $employeeIds, bool $setRoleShipping = false): void
    {
        $ids = array_filter($employeeIds, fn ($v) => $v !== '' && $v !== null);
        if (empty($ids)) {
            return;
        }
        $update = ['warehouse_id' => $warehouseId];
        if ($setRoleShipping) {
            $update['role'] = \App\Domain\Auth\Enums\UserRole::Shipping->value;
        }
        Employee::whereIn('_id', $ids)->update($update);
    }

    /**
     * Ensure the assigned manager employee has this warehouse and warehouse_manager role.
     * Adds warehouse to their warehouse_ids so they can manage multiple warehouses.
     */
    private function syncManagerToWarehouse(string $employeeId, string $warehouseId): void
    {
        $employee = Employee::find($employeeId);
        if (! $employee) {
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
        $employee->update([
            'warehouse_id' => $ids[0],
            'warehouse_ids' => $ids,
            'role' => UserRole::WarehouseManager->value,
        ]);
    }

    public function delete(string $id): bool
    {
        return $this->repository->delete($id);
    }
}
