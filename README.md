# Bookstore

Monorepo for the bookstore platform: a Laravel 11 API in `api/`, a React 19 + Vite 7 web app in `web/`, and a Flutter client in `app/`.

**Documentation:** **[project.md](./project.md)** · **[AUTHENTICATION.md](./AUTHENTICATION.md)**

This project includes a public catalog, JWT-based customer/employee auth, checkout-time stock reservation, warehouse quotes, PayPal and Stripe after quote, POS invoicing, publisher commission accounting, and multi-currency book pricing.

## Quick start

```bash
# API (requires a local MongoDB instance)
cd api
cp .env.example .env
composer install
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan storage:link
php artisan serve

# Web (another terminal)
cd web
npm install
npm run dev

# Flutter (another terminal)
cd app
flutter pub get
flutter run
```

- API: `http://localhost:8000/api/v1`
- Web: `http://localhost:5173` (Vite proxies `/api` → `:8000`)
- Flutter API URL: set at build time, for example `flutter run --dart-define=API_HOST=192.168.x.x` on a physical device or `--dart-define=API_BASE_URL=https://…/api/v1`; defaults to `10.0.2.2` on Android and `localhost` elsewhere.
- Tests: `cd api && php artisan test` (feature tests run against the `book_store_test` MongoDB database). Web: `npm run lint && npm run build`. Flutter: `flutter analyze`.
