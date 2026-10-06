<?php

namespace App\Http\Requests\Admin;

use App\Domain\Auth\Enums\UserRole;

trait MergesWarehouseIdsFromSingleId
{
    protected function mergeWarehouseIdsFromSingleId(): void
    {
        $role = (string) $this->input('role', '');
        $usesWarehouseIds = UserRole::usesWarehouseIds($role);
        if (! $usesWarehouseIds) {
            return;
        }

        $ids = $this->input('warehouse_ids');
        if (is_array($ids) && $ids !== []) {
            return;
        }

        $single = $this->input('warehouse_id');
        if (is_string($single) && $single !== '') {
            $this->merge(['warehouse_ids' => [$single]]);
        }
    }
}
