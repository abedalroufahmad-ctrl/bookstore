<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use MongoDB\BSON\ObjectId;

class SyncCountriesFromNetwork extends Command
{
    protected $signature = 'countries:sync-from-network
                            {--dry-run : Fetch and show what would be synced without writing to DB}';

    protected $description = 'Fetch countries, capital cities, and currencies from REST Countries API and sync to database';

    private const API_URL = 'https://restcountries.com/v3.1/all?fields=name,cca2,cca3,capital,currencies';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Fetching countries from REST Countries API...');
        $response = Http::timeout(30)->get(self::API_URL);

        if (! $response->successful()) {
            $this->error('Failed to fetch data: '.$response->status());

            return self::FAILURE;
        }

        $items = $response->json();
        if (! is_array($items)) {
            $this->error('Invalid API response.');

            return self::FAILURE;
        }

        $createdCountries = 0;
        $updatedCountries = 0;
        $createdCurrencies = 0;
        $updatedCurrencies = 0;
        $createdCities = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $code = $item['cca2'] ?? null;
            if (empty($code) || ! is_string($code)) {
                $skipped++;
                continue;
            }

            $name = $item['name']['common'] ?? $code;
            $capitals = $item['capital'] ?? [];
            $currencies = $item['currencies'] ?? [];
            $firstCurrency = is_array($currencies) && $currencies !== [] ? reset($currencies) : null;
            $currencyCode = $firstCurrency ? (array_key_first($currencies) ?? '') : '';
            $currencyName = $firstCurrency['name'] ?? '';
            $currencySymbol = $firstCurrency['symbol'] ?? '';

            if ($dryRun) {
                $this->line("  [dry-run] {$name} ({$code}) | currency: {$currencyCode} | capitals: ".implode(', ', $capitals));
                continue;
            }

            // Ensure one currency per country (controller looks up by countryCode); use first currency from API
            if ($currencyCode !== '') {
                $currency = Currency::query()->where('countryCode', $code)->first();
                if ($currency) {
                    $currency->update([
                        'code' => $currencyCode,
                        'name' => $currencyName,
                        'symbol' => $currencySymbol,
                        'isActive' => true,
                    ]);
                    $updatedCurrencies++;
                } else {
                    Currency::query()->create([
                        'code' => $currencyCode,
                        'symbol' => $currencySymbol,
                        'name' => $currencyName,
                        'nameArabic' => null,
                        'exchangeRate' => 1.0,
                        'isActive' => true,
                        'isDefault' => false,
                        'countryCode' => $code,
                    ]);
                    $createdCurrencies++;
                }
            }

            // Upsert country (use updateOrCreate to avoid hydrating model and triggering cast on read)
            $country = Country::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'translations' => ['en' => $name]]
            );
            if ($country->wasRecentlyCreated) {
                $createdCountries++;
            } else {
                $updatedCountries++;
            }

            $countryId = $country->getKey();
            $countryIdBson = $countryId instanceof ObjectId ? $countryId : new ObjectId((string) $countryId);

            // Ensure capital cities exist
            foreach ($capitals as $capitalName) {
                if (trim((string) $capitalName) === '') {
                    continue;
                }
                $exists = City::query()
                    ->where('countryId', $countryIdBson)
                    ->where('name', $capitalName)
                    ->exists();
                if (! $exists) {
                    City::query()->create([
                        'name' => $capitalName,
                        'countryId' => $countryIdBson,
                        'translations' => ['en' => $capitalName],
                    ]);
                    $createdCities++;
                }
            }
        }

        if ($dryRun) {
            $this->info('Dry run complete. Count: '.count($items).' entries.');

            return self::SUCCESS;
        }

        $this->info('Sync complete.');
        $this->table(
            ['Resource', 'Created', 'Updated'],
            [
                ['Countries', $createdCountries, $updatedCountries],
                ['Currencies', $createdCurrencies, $updatedCurrencies],
                ['Cities (capitals)', $createdCities, '-'],
            ]
        );
        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} entries (no valid cca2).");
        }

        return self::SUCCESS;
    }
}
