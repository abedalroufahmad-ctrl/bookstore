<?php

namespace App\Domain\Book\Enums;

enum BookCondition: string
{
    case New = 'new';
    case Used = 'used';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function normalize(?string $value): self
    {
        $raw = strtolower(trim((string) $value));
        if (in_array($raw, ['used', 'مستعمل', 'مستخدمة', 'مستخدم'], true)) {
            return self::Used;
        }

        return self::New;
    }
}
