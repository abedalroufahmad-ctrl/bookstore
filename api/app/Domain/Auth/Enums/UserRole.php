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
}
