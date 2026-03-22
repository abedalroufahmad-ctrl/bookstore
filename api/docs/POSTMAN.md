# Testing the API with Postman

The API uses **JWT (Bearer token)** for authenticated endpoints. Do **not** use Basic Auth.

## Base URL

- Local: `http://localhost:8000/api/v1` (or `http://0.0.0.0:8000/api/v1`)

---

## 1. Get a customer token (login)

**Request**

- **Method:** `POST`
- **URL:** `{{baseUrl}}/customers/login`
- **Headers:** `Content-Type: application/json`
- **Body (raw JSON):**

```json
{
  "email": "your-customer@example.com",
  "password": "your-password"
}
```

**Response**

You will get a JSON body like:

```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "customer": { "_id": "...", "name": "...", "email": "..." },
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "token_type": "bearer",
    "expires_in": 3600
  }
}
```

**Copy the `data.token` value** — you will use it as the Bearer token in the next steps.

---

## 2. Call protected endpoints (e.g. GET /customers/me)

**Request**

- **Method:** `GET`
- **URL:** `{{baseUrl}}/customers/me`
- **Authorization:**
  - Type: **Bearer Token**
  - Token: paste the token from step 1

Do **not** use Basic Auth. Using Basic Auth causes the server to return an error (and previously caused "Route [login] not defined").

**Response**

```json
{
  "success": true,
  "message": "...",
  "data": {
    "_id": "...",
    "name": "...",
    "email": "...",
    "address": "...",
    "city": "...",
    "country": "...",
    "postal_code": "...",
    "phone": "..."
  }
}
```

---

## 3. Optional: save the token in Postman

1. After login, in the **Tests** tab of the login request, add:

```javascript
var json = pm.response.json();
if (json.success && json.data && json.data.token) {
  pm.environment.set("customer_token", json.data.token);
}
```

2. Create an **Environment** with variable `customer_token` (leave value empty).
3. For **GET /customers/me**, set Authorization to **Bearer Token** and use `{{customer_token}}`.

---

## Employee endpoints

- **Login:** `POST {{baseUrl}}/employees/login` with body `{ "email": "...", "password": "..." }`.
- Use the returned token as **Bearer Token** for admin routes under `{{baseUrl}}/admin/...`.

---

## Summary

| Step | Auth type   | What to do                                      |
|------|-------------|--------------------------------------------------|
| 1    | None        | `POST /customers/login` with email + password   |
| 2    | Bearer Token| Use `data.token` from step 1 for `/customers/me` and other protected customer routes |

If you get **401 Unauthenticated**, the token is missing, wrong, or expired. Log in again to get a new token.
