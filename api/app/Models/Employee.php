<?php

namespace App\Models;

use App\Domain\Auth\Enums\UserRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MongoDB\Laravel\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Employee extends Authenticatable implements JWTSubject
{
    protected $connection = 'mongodb';

    protected $table = 'employees';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'warehouse_id',
        'warehouse_ids',
        'publisher_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'warehouse_ids' => 'array',
        ];
    }

    /**
     * Whether this employee (warehouse manager) can manage the given warehouse.
     */
    public function managesWarehouse(string $warehouseId): bool
    {
        if ($this->role !== UserRole::WarehouseManager->value) {
            return (string) $this->warehouse_id === (string) $warehouseId;
        }
        $ids = $this->warehouse_ids ?? [];
        if (! empty($ids) && is_array($ids)) {
            return in_array($warehouseId, $ids, true) || in_array((string) $warehouseId, array_map('strval', $ids), true);
        }

        return (string) $this->warehouse_id === (string) $warehouseId;
    }

    /**
     * Warehouse IDs this employee can manage (for warehouse_manager: warehouse_ids, else single warehouse_id).
     */
    public function getManagedWarehouseIds(): array
    {
        if ($this->role === UserRole::WarehouseManager->value) {
            $ids = $this->warehouse_ids ?? [];
            if (is_array($ids) && ! empty($ids)) {
                return array_values(array_map('strval', $ids));
            }
        }
        if (! empty($this->warehouse_id)) {
            return [(string) $this->warehouse_id];
        }

        return [];
    }

    /**
     * The publisher this employee manages, if any (publisher_manager role).
     */
    public function getManagedPublisherId(): ?string
    {
        return ! empty($this->publisher_id) ? (string) $this->publisher_id : null;
    }

    /**
     * Whether this employee (publisher manager) manages the given publisher.
     */
    public function managesPublisher(?string $publisherId): bool
    {
        $mine = $this->getManagedPublisherId();

        return $mine !== null && $publisherId !== null && $mine === (string) $publisherId;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
            'guard' => 'employee',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }
}
