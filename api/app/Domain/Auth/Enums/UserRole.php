<?php

namespace App\Domain\Auth\Enums;

enum UserRole: string
{
    case Manager = 'manager';
    case Shipping = 'shipping';
    case Review = 'review';
    case Accounting = 'accounting';
    case Driver = 'driver';
    case WarehouseManager = 'warehouse_manager';
    case PublisherManager = 'publisher_manager';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Shipping => 'Shipping',
            self::Review => 'Review',
            self::Accounting => 'Accounting',
            self::Driver => 'Driver',
            self::WarehouseManager => 'Warehouse Manager',
            self::PublisherManager => 'Publisher Manager',
            self::Customer => 'Customer',
        };
    }

    /**
     * Roles that can access the admin panel.
     */
    public static function adminRoles(): array
    {
        return [
            self::Manager->value,
            self::Shipping->value,
            self::Review->value,
            self::Accounting->value,
            self::WarehouseManager->value,
            self::PublisherManager->value,
        ];
    }

    /**
     * Roles that can manage orders.
     */
    public static function orderManagementRoles(): array
    {
        return [
            self::Manager->value,
            self::Shipping->value,
            self::Accounting->value,
            self::WarehouseManager->value,
        ];
    }

    /**
     * Role that is scoped to a single warehouse (no cross-warehouse access).
     */
    public static function isWarehouseScoped(string $role): bool
    {
        return $role === self::WarehouseManager->value;
    }

    /**
     * Role scoped to a single publisher: manages only warehouses and books
     * belonging to that publisher (plus the shared author library).
     */
    public static function isPublisherScoped(string $role): bool
    {
        return $role === self::PublisherManager->value;
    }

    /**
     * Roles that can see and manage all warehouses (no scoping).
     */
    public static function canManageAllWarehouses(string $role): bool
    {
        return $role === self::Manager->value;
    }

    /**
     * Staff who are limited to assigned warehouse(s).
     */
    public static function isLimitedToAssignedWarehouses(string $role): bool
    {
        return in_array($role, [
            self::WarehouseManager->value,
            self::Shipping->value,
            self::Accounting->value,
        ], true);
    }

    /**
     * Staff who must only see/act on orders for their warehouse(s).
     */
    public static function isOrderWarehouseScoped(string $role): bool
    {
        return in_array($role, [
            self::WarehouseManager->value,
            self::Shipping->value,
            self::Accounting->value,
        ], true);
    }

    /**
     * Roles a warehouse manager may assign when adding/updating staff in their warehouse(s).
     */
    public static function warehouseManagerStaffRoles(): array
    {
        return [
            self::Shipping->value,
            self::Accounting->value,
        ];
    }

    /**
     * Roles a publisher manager may assign for staff linked to their publisher.
     */
    public static function publisherManagerStaffRoles(): array
    {
        return [
            self::Shipping->value,
            self::Review->value,
            self::Accounting->value,
            self::WarehouseManager->value,
            self::PublisherManager->value,
        ];
    }
}
