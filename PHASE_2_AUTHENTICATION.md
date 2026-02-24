# PHASE 2: Authentication System — Complete

> **Status:** Implemented  
> **Next Phase:** PHASE 3 — Core Domain Models

---

## Summary

Authentication system with separate Employee and Customer flows, JWT, and role-based middleware.

---

## 1. Employee Auth

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `POST /api/v1/employees/login` | POST | No | Login with email + password |
| `POST /api/v1/employees/logout` | POST | JWT | Logout (blacklist token) |
| `POST /api/v1/employees/refresh` | POST | JWT | Refresh token |
| `GET /api/v1/employees/me` | GET | JWT | Get current employee |

**Notes:**
- Employees are created by admin (no public register).
- Guard: `auth:employee`
- JWT includes `role` and `guard: employee` in claims.

---

## 2. Customer Auth

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `POST /api/v1/customers/register` | POST | No | Register new customer |
| `POST /api/v1/customers/login` | POST | No | Login with email + password |
| `POST /api/v1/customers/logout` | POST | JWT | Logout |
| `POST /api/v1/customers/refresh` | POST | JWT | Refresh token |
| `GET /api/v1/customers/me` | GET | JWT | Get current customer |
| `PUT /api/v1/customers/profile` | PUT | JWT | Update profile |

**Register fields:** `name`, `email`, `password`, `password_confirmation`, `address`, `country`, `city`, `phone` (optional).

---

## 3. JWT Configuration

- **Config:** `config/jwt.php`
- **Env:** `JWT_SECRET`, `JWT_TTL`, `JWT_REFRESH_TTL`, `JWT_BLACKLIST_ENABLED`
- **Per-guard:** Employee and Customer use separate providers (same JWT secret, different models).

---

## 4. Role Middleware

```php
Route::middleware(['auth:employee', 'role:manager,shipping,review,accounting,employee'])->group(...);
```

- **Alias:** `role`
- **Usage:** `role:role1,role2,...,guard` (guard optional; inferred from auth if omitted)
- **403** when role is missing or insufficient.

---

## 5. API Response Format

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "employee": { ... },
    "token": "...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

---

## 6. Clean Architecture Additions

- **Domain:** `app/Domain/Auth/Enums/UserRole.php`, `Interfaces/EmployeeAuthServiceInterface.php`, `Interfaces/CustomerAuthServiceInterface.php`
- **Services:** Implement interfaces; bound in `AppServiceProvider`
- **Controllers:** Inject interfaces (dependency inversion)

---

## 7. Setup

```bash
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
composer install
php artisan migrate  # MongoDB migrations
php artisan db:seed  # Seed employee for testing
php artisan serve
```

**Seed:** `EmployeeSeeder` creates a default manager (check `database/seeders/EmployeeSeeder.php` for credentials).

---

**PHASE 2 COMPLETE — Proceed to PHASE 3 when ready.**
