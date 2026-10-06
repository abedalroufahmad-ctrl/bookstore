# Bookstore

Monorepo: Laravel API (`api/`), React web (`web/`), Flutter app (`app/`).

**Full documentation:** **[project.md](./project.md)** · Authentication: **[AUTHENTICATION.md](./AUTHENTICATION.md)**

## Quick start

```bash
# API
cd api && cp .env.example .env && composer install \
  && php artisan key:generate && php artisan jwt:secret \
  && php artisan migrate && php artisan storage:link && php artisan serve

# Web (another terminal)
cd web && npm install && npm run dev

# Flutter (another terminal)
cd app && flutter pub get && flutter run
```

- API: `http://localhost:8000/api/v1`
- Web: `http://localhost:5173` (proxies `/api` → `:8000`)
- Flutter API URL: set at build time, e.g. `flutter run --dart-define=API_HOST=192.168.x.x` (physical device) or `--dart-define=API_BASE_URL=https://…/api/v1`; defaults to `10.0.2.2` on the Android emulator and `localhost` elsewhere.
- Tests: `cd api && php artisan test` (feature tests use the `book_store_test` MongoDB database).
