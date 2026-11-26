<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\PresentsDocumentId;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use PresentsDocumentId;

    protected $table = 'cities';

    protected $fillable = ['name', 'country_id', 'translations'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    protected function casts(): array
    {
        return [
            'translations' => 'array',
        ];
    }
}
