<?php

namespace App\Domain\Auth\Enums;

enum UserRole: string
{
    case Manager = 'manager';
    case Shipping = 'shipping';
    case Review = 'review';
    case Accounting = 'accounting';
    case Driver = 'driver';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Shipping => 'Shipping',
            self::Review => 'Review',
            self::Accounting => 'Accounting',
            self::Driver => 'Driver',
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
        ];
    }
}
