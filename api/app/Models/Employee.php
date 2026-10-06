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
     * Whether this employee (warehouse manager or shipping) can manage the given warehouse.
     */
    public function managesWarehouse(string $warehouseId): bool
    {
        if (! UserRole::usesWarehouseIds((string) ($this->role ?? ''))) {
            return (string) $this->warehouse_id === (string) $warehouseId;
        }
        $ids = $this->warehouse_ids ?? [];
        if (! empty($ids) && is_array($ids)) {
            return in_array($warehouseId, $ids, true) || in_array((string) $warehouseId, array_map('strval', $ids), true);
        }

        return (string) $this->warehouse_id === (string) $warehouseId;
    }

    /**
     * Warehouse IDs this employee can manage (for warehouse_manager and shipping: warehouse_ids, else single warehouse_id).
     */
    public function getManagedWarehouseIds(): array
    {
        if (UserRole::usesWarehouseIds((string) ($this->role ?? ''))) {
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

    /**
     * Whether the given employee belongs to this publisher manager's publishing house.
     * - Peer publisher managers: same publisher_id
     * - All other staff: at least one warehouse must belong to that publisher
     *   (a bare publisher_id alone is not enough — prevents cross-house leakage)
     *
     * @param  list<string>  $publisherWarehouseIds
     */
    public function employeeBelongsToPublisher(Employee $employee, array $publisherWarehouseIds): bool
    {
        $publisherId = $this->getManagedPublisherId();
        if ($publisherId === null) {
            return false;
        }

        // Never treat global managers as publisher-scoped staff.
        if ((string) ($employee->role ?? '') === UserRole::Manager->value) {
            return false;
        }

        if ((string) ($employee->role ?? '') === UserRole::PublisherManager->value) {
            return (string) ($employee->publisher_id ?? '') === $publisherId;
        }

        $publisherWarehouseIds = array_values(array_map('strval', $publisherWarehouseIds));
        if ($publisherWarehouseIds === []) {
            return false;
        }

        $wid = (string) ($employee->warehouse_id ?? '');
        if ($wid !== '' && in_array($wid, $publisherWarehouseIds, true)) {
            return true;
        }

        $ids = $employee->warehouse_ids ?? [];
        if (is_array($ids)) {
            foreach ($ids as $id) {
                if (in_array((string) $id, $publisherWarehouseIds, true)) {
                    return true;
                }
            }
        }

        return false;
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
