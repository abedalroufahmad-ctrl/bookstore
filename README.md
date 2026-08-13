# Bookstore

Monorepo: Laravel API (`api/`), React web (`web/`), Flutter app (`app/`).

**Full documentation:** **[project.md](./project.md)**

## Quick start

```bash
# API
cd api && cp .env.example .env && composer install \
  && php artisan key:generate && php artisan jwt:secret \
  && php artisan migrate && php artisan serve

# Web (another terminal)
cd web && npm install && npm run dev

# Flutter (another terminal)
cd app && flutter pub get && flutter run
```

- API: `http://localhost:8000/api/v1`
- Web: `http://localhost:5173` (proxies `/api` → `:8000`)
- Flutter API URL: `app/lib/config.dart`
