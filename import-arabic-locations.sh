#!/usr/bin/env bash

set -euo pipefail

# Download Arabic countries, states, and cities from GitHub
# Source: https://github.com/FB-Technologies-algerie/arabic-countries-states-cities
# Structure: countries (id=phone_code, code=ISO), states (country_id), cities (state_id)

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DATA_DIR="$ROOT_DIR/api/database/seeders/data/arabic-locations"

mkdir -p "$DATA_DIR"

echo "Downloading Arabic locations data into: $DATA_DIR"

BASE_URL="https://raw.githubusercontent.com/FB-Technologies-algerie/arabic-countries-states-cities/main"

curl -fsSL "$BASE_URL/countries-no-comments.json" -o "$DATA_DIR/countries.json"
curl -fsSL "$BASE_URL/states-no-comments.json" -o "$DATA_DIR/states.json"
curl -fsSL "$BASE_URL/cities-no-comments.json" -o "$DATA_DIR/cities.json"

echo "Done. Files downloaded:"
ls -la "$DATA_DIR"

echo
echo "Run: cd api && php artisan db:seed --class=ArabicLocationsSeeder"
