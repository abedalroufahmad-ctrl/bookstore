<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use MongoDB\BSON\ObjectId;

class CountryController extends BaseApiController
{
    /**
     * List countries with their cities and currency for admin.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $query = Country::query();
        if ($request->filled('search')) {
            $s = trim((string) $request->get('search'));
            if ($s !== '') {
                $query->where(function ($q) use ($s) {
                    $q->where('name', 'like', "%{$s}%")
                        ->orWhere('code', 'like', "%{$s}%");
                });
            }
        }
        $countries = $query->orderBy('name')->paginate($perPage);

        $items = $countries->getCollection()->map(function (Country $country) {
            return $this->formatCountry($country);
        });
        $countries->setCollection($items);

        return $this->successResponse($countries);
    }

    /**
     * Sync countries, capital cities, and currencies from REST Countries API.
     */
    public function syncFromNetwork(Request $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run');
        Artisan::call('countries:sync-from-network', ['--dry-run' => $dryRun]);
        $output = trim(Artisan::output());

        return $this->successResponse([
            'message' => $dryRun ? 'Dry run completed' : 'Sync completed',
            'output' => $output,
        ]);
    }

    /**
     * Sync many more cities from an external dataset (GitHub JSON).
     */
    public function syncCitiesFromDataset(Request $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run');
        $limit = $request->integer('limit', 0);
        $options = ['--dry-run' => $dryRun];
        if ($limit > 0) {
            $options['--limit'] = $limit;
        }
        Artisan::call('countries:sync-cities-from-dataset', $options);
        $output = trim(Artisan::output());

        return $this->successResponse([
            'message' => $dryRun ? 'Dry run completed' : 'Cities sync completed',
            'output' => $output,
        ]);
    }

    /**
     * Get a single country with cities and currency.
     */
    public function show(string $id): JsonResponse
    {
        $country = Country::query()->find($id);
        if (! $country) {
            return $this->errorResponse('Country not found', 404);
        }

        return $this->successResponse($this->formatCountry($country));
    }

    private function formatCountry(Country $country): array
    {
        $countryId = $country->getKey();
        $code = $country->code ?? '';

        try {
            $countryIdBson = $countryId instanceof ObjectId ? $countryId : new ObjectId((string) $countryId);
        } catch (\Throwable) {
            $countryIdBson = $countryId;
        }

        $cities = City::query()
            ->where('countryId', $countryIdBson)
            ->orderBy('name')
            ->get()
            ->map(fn ($c) => [
                'id' => (string) $c->getKey(),
                'name' => $c->name ?? (is_array($c->translations) ? ($c->translations['en'] ?? '') : ''),
            ])
            ->values()
            ->all();

        $currency = Currency::query()
            ->where('countryCode', $code)
            ->first();
        if (! $currency) {
            $currency = Currency::query()->first();
        }

        return [
            '_id' => (string) $countryId,
            'name' => $country->name ?? '',
            'code' => $code,
            'currency_code' => $currency ? ($currency->code ?? '') : '',
            'currency_name' => $currency ? ($currency->name ?? $currency->nameArabic ?? '') : '',
            'cities' => $cities,
        ];
    }
}
