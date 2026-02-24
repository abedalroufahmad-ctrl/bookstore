# PHASE 7: Testing Strategy

> **Status:** Implemented  
> **Project:** Enterprise Book Store System

---

## 1. Testing Stack

| Tool | Purpose |
|------|---------|
| PHPUnit 11 | Test runner |
| Laravel HTTP Tests | API testing |
| Faker | Test data generation |
| MongoDB | Test database (`book_store_test`) |

---

## 2. Test Structure

```
tests/
├── TestCase.php              # Base test case
├── ApiTestCase.php           # API-specific base (auth helpers)
├── CreatesApplication.php
├── Unit/
│   ├── Domain/               # Enum, DTO logic
│   ├── Services/             # Service unit tests
│   └── Repositories/         # Repository tests (with DB)
├── Feature/
│   ├── Auth/
│   │   ├── EmployeeAuthTest.php
│   │   └── CustomerAuthTest.php
│   ├── PublicCatalogTest.php
│   ├── CartTest.php
│   ├── OrderTest.php
│   └── Admin/
│       ├── BookTest.php
│       ├── EmployeeTest.php
│       └── ...
└── Helpers/
    └── TestDataFactory.php   # Factory helpers
```

---

## 3. Test Categories

### Unit Tests
- **Domain:** Enums (OrderStatus, UserRole), DTOs
- **Services:** Business logic with mocked repositories
- **StockService:** validateAndDeduct, restore logic

### Feature Tests (API)
- **Auth:** Login, logout, refresh, register
- **Public Catalog:** Books, categories, authors (no auth)
- **Cart:** Add, remove, update (customer auth)
- **Order:** Checkout, status, cancel
- **Admin:** CRUD for books, employees, etc.

---

## 4. Test Database

- **Connection:** `mongodb` (same as prod)
- **Database:** `book_store_test` (via `MONGODB_DATABASE` in phpunit.xml)
- **Isolation:** Each test should clean up or use unique data
- **Recommendation:** Use `RefreshDatabase`-style cleanup or run migration before tests

---

## 5. Auth Helpers

```php
// Employee login
$token = $this->loginAsEmployee($email, $password);

// Customer login
$token = $this->loginAsCustomer($email, $password);

// Request with auth
$response = $this->withToken($token)->getJson('/api/v1/customers/me');
```

---

## 6. API Response Assertions

```php
$response->assertSuccess()
    ->assertJsonStructure(['success', 'message', 'data']);

$response->assertSuccess()
    ->assertJsonPath('data.token', $expected);

$response->assertError(422, 'Validation failed.');
```

---

## 7. Running Tests

```bash
# All tests
php artisan test

# With coverage
php artisan test --coverage

# Specific suite
php artisan test --testsuite=Feature

# Specific test
php artisan test --filter=EmployeeAuthTest
```

---

## 8. CI/CD Considerations

- Use `MONGODB_URI` for test MongoDB (e.g. `mongodb://localhost:27017`)
- Set `CACHE_CATALOG_ENABLED=false` in phpunit.xml for faster, deterministic tests
- Exclude `vendor/`, `tests/` from coverage if desired
- Run tests before deploy

---

## 9. Test Coverage Goals

| Layer | Target | Priority |
|-------|--------|----------|
| Auth | 90%+ | High |
| Cart/Order | 85%+ | High |
| Admin CRUD | 80%+ | Medium |
| Public Catalog | 75%+ | Medium |
| Domain/Enums | 100% | Low |

---

---

## 10. Existing Tests

| Test | Description |
|------|-------------|
| OrderStatusTest | Unit: enum values, canBeCancelled, labels |
| EmployeeAuthTest | Login, invalid credentials, me with token, unauthenticated |
| CustomerAuthTest | Register, login, validation |
| PublicCatalogTest | Books, categories, authors (no auth) |
| ExampleTest | Root & API response format |

---

**PHASE 7 COMPLETE — Testing strategy implemented.**
