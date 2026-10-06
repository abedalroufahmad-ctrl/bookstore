<?php

use App\Models\Publisher;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::connection('mongodb')->table('warehouses', function (Blueprint $collection) {
            $collection->index('publisher_id', options: ['name' => 'warehouses_publisher_id_index']);
        });

        $defaultPublisher = Publisher::orderBy('created_at')->first();
        if ($defaultPublisher) {
            Warehouse::query()
                ->where(function ($q) {
                    $q->whereNull('publisher_id')
                        ->orWhere('publisher_id', '');
                })
                ->update(['publisher_id' => (string) $defaultPublisher->getKey()]);
        }
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('warehouses', function (Blueprint $collection) {
            $collection->dropIndex(['publisher_id']);
        });
    }
};
