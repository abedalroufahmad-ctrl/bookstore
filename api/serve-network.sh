#!/bin/bash
# Run API server accessible from network (e.g. Flutter app on physical device)
# Use this when testing from phone/tablet on same WiFi
cd "$(dirname "$0")"
php artisan serve --host=0.0.0.0
