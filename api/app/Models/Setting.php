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
}
