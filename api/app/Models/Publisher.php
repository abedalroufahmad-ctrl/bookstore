<?php

namespace App\Models;

use App\Models\Concerns\BustsCatalogCache;
use Illuminate\Database\Eloquent\Relations\HasMany;
use MongoDB\Laravel\Eloquent\Model;

class Publisher extends Model
{
    use BustsCatalogCache;

    protected $connection = 'mongodb';

    protected $table = 'publishers';

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'website',
        'settings',
        'commission_owed',
        'commission_accrued_total',
        'commission_paid_total',
    ];

    protected $casts = [
        'settings' => 'array',
        'commission_owed' => 'float',
        'commission_accrued_total' => 'float',
        'commission_paid_total' => 'float',
    ];

    /**
     * Raw settings hold payout data (bank account, PayPal ids, commission) and must only be
     * returned by the dedicated admin settings endpoint. Running commission balances are
     * internal to managers / publisher managers.
     */
    protected $hidden = [
        'settings',
        'commission_owed',
        'commission_accrued_total',
        'commission_paid_total',
    ];

    protected $appends = ['public_settings'];

    /** Settings keys that are safe to show customers and anonymous visitors. */
    public const PUBLIC_SETTING_KEYS = [
        'payment_methods',
        'support_email',
        'support_phone',
        'return_policy',
    ];

    public function getPublicSettingsAttribute(): array
    {
        $settings = is_array($this->settings) ? $this->settings : [];

        return array_intersect_key($settings, array_flip(self::PUBLIC_SETTING_KEYS));
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
