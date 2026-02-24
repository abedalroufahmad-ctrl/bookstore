# Book Store API (Laravel 11 + MongoDB + JWT)

API-only Laravel 11 backend with MongoDB, JWT authentication, Repository pattern, Service layer, Form Request validation, and role-based middleware.

## Requirements

- PHP 8.2+
- Composer
- MongoDB 4.4+
- Extensions: mongodb, openssl, json, mbstring, tokenizer

## Install

```bash
cd api
cp .env.example .env
composer install
php artisan key:generate
php artisan jwt:secret
```

## Environment

- Copy `.env.example` to `.env`
- Set `MONGODB_URI` (e.g. `mongodb://localhost:27017`) and `MONGODB_DATABASE` (e.g. `book_store`)
- JWT keys are created by `php artisan jwt:secret`

## Run

```bash
cd api
php artisan serve
```

For Flutter app on physical device (same WiFi): use `--host=0.0.0.0` so the phone can connect:

```bash
php artisan serve --host=0.0.0.0
```

API base: `http://localhost:8000/api/v1`  
API docs: [http://localhost:8000/docs.html](http://localhost:8000/docs.html) (when server is running)

## API Response Format

All responses use this structure:

```json
{
  "success": true,
  "message": "",
  "data": {}
}
```

Errors (including validation) use `success: false` and appropriate HTTP status.

## Auth Endpoints

| Method | Endpoint        | Auth  | Description   |
|--------|-----------------|-------|---------------|
| POST   | /api/v1/register | No    | Register      |
| POST   | /api/v1/login    | No    | Login         |
| GET    | /api/v1/me       | JWT   | Current user  |
| POST   | /api/v1/refresh  | JWT   | Refresh token |
| POST   | /api/v1/logout   | JWT   | Logout        |

Send JWT in header: `Authorization: Bearer <token>`.

## Folder Structure

```
api/
├── app/
│   ├── Exceptions/
│   ├── Http/Controllers/Api/
│   ├── Middleware/
│   ├── Models/
│   ├── Repositories/
│   └── Services/
├── config/
├── routes/
│   ├── api.php               # /api/v1 routes
│   ├── web.php
│   └── console.php
└── ...
```

## Role Middleware

Use `role:admin` or `role:user` on routes:

```php
Route::middleware(['jwt.auth', 'role:admin'])->group(function () {
    // admin only
});
```

## Composer Commands

```bash
cd api
# Install dependencies
composer install

# After clone (first time)
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

## Seed (optional)

```bash
cd api
php artisan db:seed
# Creates manager@bookstore.test / password
```

## CORS

Configure allowed origins in `.env`:

```
CORS_ALLOWED_ORIGINS=https://app.example.com,http://localhost:5173
# or * for all
CORS_ALLOWED_ORIGINS=*
```

## Frontend

React + Vite + TypeScript SPA in `frontend/`:

```bash
cd frontend
npm install
npm run dev
```

- **Store:** Browse books, cart, checkout (customer login)
- **Admin:** Books, orders, employees (employee login)

Proxy: `/api` → `http://localhost:8000`

## Flutter App

Mobile app in `app/`:

```bash
cd app
flutter pub get
flutter run
```

- **Store:** Browse books, cart, checkout (customer)
- **Auth:** Login (customer/employee), register
- **Config:** Set API URL in `lib/config.dart` (e.g. `10.0.2.2:8000` for Android emulator)
