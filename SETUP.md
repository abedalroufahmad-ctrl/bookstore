# Setup Commands

Run from `api/` directory:

```bash
cd api

# 1. Install dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# 3. (Optional) Seed admin user
php artisan db:seed

# 4. Run server
php artisan serve
```

## Admin accounts (after seed)

| Email | Password | Role |
|-------|----------|------|
| admin@bookstore.test | password | manager |
| manager@bookstore.test | password | manager |
| shipping@bookstore.test | password | shipping |

## API base URL

- **Local:** http://localhost:8000/api/v1  

## Required PHP extensions

- mongodb
- openssl
- json
- mbstring
- tokenizer
- xml
- ctype
