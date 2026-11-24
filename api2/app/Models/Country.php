<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\PresentsDocumentId;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use PresentsDocumentId;

    protected $table = 'countries';

    protected $fillable = ['name', 'code', 'translations'];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class, 'country_id');
    }

    /**
     * MongoDB stores/returns translations as native array; do not use 'array' cast
     * (Laravel's array cast uses json_decode which fails when value is already array).
     */
    protected function getTranslationsAttribute($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
