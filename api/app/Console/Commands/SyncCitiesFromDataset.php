<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Country;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use MongoDB\BSON\ObjectId;

class SyncCitiesFromDataset extends Command
{
    protected $signature = 'countries:sync-cities-from-dataset
                            {--dry-run : Show what would be synced without writing to DB}
                            {--limit= : Max cities to add per country (default: no limit)}';

    protected $description = 'Fetch a large cities dataset (GitHub) and add cities to existing countries';

    /** Country name in our DB => key in the external dataset (when they differ). */
    private const COUNTRY_NAME_ALIASES = [
        'Czechia' => 'Czech Republic',
        'North Macedonia' => 'Macedonia',
        'South Korea' => 'Republic of Korea',
        'Côte d\'Ivoire' => 'Ivory Coast',
        'Ivory Coast' => 'Ivory Coast',
    ];

    private const DATASET_URL = 'https://raw.githubusercontent.com/ToniCifre/all-countries-and-cities-json/master/countries.json';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limitPerCountry = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info('Fetching cities dataset from GitHub...');
        $response = Http::timeout(60)->get(self::DATASET_URL);

        if (! $response->successful()) {
            $this->error('Failed to fetch dataset: '.$response->status());

            return self::FAILURE;
        }

        $dataset = $response->json();
        if (! is_array($dataset)) {
            $this->error('Invalid dataset format.');

            return self::FAILURE;
        }

        $countries = Country::query()->orderBy('name')->get();
        $created = 0;
        $skippedNoMatch = 0;
        $skippedExists = 0;

        foreach ($countries as $country) {
            $countryName = $country->name ?? '';
            $datasetKey = self::COUNTRY_NAME_ALIASES[$countryName] ?? $countryName;
            if (! isset($dataset[$datasetKey]) || ! is_array($dataset[$datasetKey])) {
                $skippedNoMatch++;
                continue;
            }

            $cityNames = array_values(array_unique($dataset[$datasetKey]));
            if ($limitPerCountry !== null && $limitPerCountry > 0) {
                $cityNames = array_slice($cityNames, 0, $limitPerCountry);
            }

            $countryId = $country->getKey();
            $countryIdBson = $countryId instanceof ObjectId ? $countryId : new ObjectId((string) $countryId);

            foreach ($cityNames as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }

                $exists = City::query()
                    ->where('countryId', $countryIdBson)
                    ->where('name', $name)
                    ->exists();

                if ($exists) {
                    $skippedExists++;
                    continue;
                }

                if (! $dryRun) {
                    City::query()->create([
                        'name' => $name,
                        'countryId' => $countryIdBson,
                        'translations' => ['en' => $name],
                    ]);
                }
                $created++;
            }
        }

        if ($dryRun) {
            $this->info("Dry run: would add {$created} new cities (skipped {$skippedExists} existing, {$skippedNoMatch} countries without dataset match).");
        } else {
            $this->info("Added {$created} cities. Skipped {$skippedExists} already existing, {$skippedNoMatch} countries without match in dataset.");
        }

        return self::SUCCESS;
    }
}
