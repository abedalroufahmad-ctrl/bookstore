<?php

namespace App\Domain\Cart\Enums;

enum CartStatus: string
{
    case Active = 'active';
    case Converted = 'converted';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Converted => 'Converted to Order',
        };
    }
}
