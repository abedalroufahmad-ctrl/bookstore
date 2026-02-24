# app

Book Store - Flutter mobile app for the Book Store API.

## Setup

1. Ensure the API is running: `cd api && php artisan serve`
2. Update `lib/config.dart` with your API URL:
   - Android emulator: `http://10.0.2.2:8000/api/v1`
   - iOS simulator: `http://localhost:8000/api/v1`
   - Physical device: `http://YOUR_IP:8000/api/v1`

## Run

```bash
cd app
flutter pub get
flutter run
```

## Features

- Browse books (public catalog)
- Customer: Register, login, cart, checkout, orders
- Employee: Login, admin access (placeholder)
