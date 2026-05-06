<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Setting extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'settings';

    protected $fillable = ['key', 'value'];

    protected static array $cache = [];

    public static function get(string $key, $default = null)
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        $setting = self::where('key', $key)->first();
        $value = $setting ? $setting->value : $default;

        self::$cache[$key] = $value;

        return $value;
    }

    public static function set(string $key, $value)
    {
        self::$cache[$key] = $value;

        return self::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Default payment options (must match public GET /settings normalization).
     */
    public static function defaultPaymentMethods(): array
    {
        return [
            ['id' => 'cod', 'name' => 'Cash on Delivery (COD)', 'enabled' => true],
            ['id' => 'stripe', 'name' => 'Credit/Debit Card (Stripe)', 'enabled' => false],
            ['id' => 'paypal', 'name' => 'PayPal', 'enabled' => false],
        ];
    }

    /**
     * IDs of enabled payment methods (supports both stored shapes: list of objects or legacy id => bool map).
     * Falls back to defaults when nothing is stored yet (same behaviour as SettingController::index for customers).
     */
    public static function enabledPaymentMethodIds(): array
    {
        $list = self::get('payment_methods');
        if (! is_array($list) || $list === []) {
            $list = self::defaultPaymentMethods();
        }

        $ids = self::parseEnabledPaymentMethodIdsFromStoredValue($list);
        if ($ids !== []) {
            return $ids;
        }

        return self::parseEnabledPaymentMethodIdsFromStoredValue(self::defaultPaymentMethods());
    }

    /**
     * @param  array<int|string, mixed>  $list
     * @return list<string>
     */
    private static function parseEnabledPaymentMethodIdsFromStoredValue(array $list): array
    {
        if (isset($list[0]) && is_array($list[0])) {
            $ids = [];
            foreach ($list as $item) {
                if (! is_array($item) || empty($item['id'])) {
                    continue;
                }
                $enabled = $item['enabled'] ?? false;
                if (filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
                    $ids[] = (string) $item['id'];
                }
            }

            return $ids;
        }

        $ids = [];
        foreach ($list as $id => $enabled) {
            if (is_string($id) && filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
