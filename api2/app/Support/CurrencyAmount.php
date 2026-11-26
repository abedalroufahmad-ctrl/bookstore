<?php

namespace App\Support;

final class CurrencyAmount
{
    private const ZERO_DECIMAL = [
        'BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG',
        'RWF', 'UGX', 'UYI', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    private const THREE_DECIMAL = ['BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND'];

    public static function precision(string $currencyCode): int
    {
        $code = strtoupper($currencyCode);
        if (in_array($code, self::ZERO_DECIMAL, true)) {
            return 0;
        }
        if (in_array($code, self::THREE_DECIMAL, true)) {
            return 3;
        }

        return 2;
    }

    public static function round(float $amount, string $currencyCode): float
    {
        return round($amount, self::precision($currencyCode));
    }

    public static function minorUnits(float $amount, string $currencyCode): int
    {
        return (int) round($amount * (10 ** self::precision($currencyCode)));
    }

    public static function decimalString(float $amount, string $currencyCode): string
    {
        return number_format($amount, self::precision($currencyCode), '.', '');
    }
}
