# PHASE 4: Cart & Order Domain — Complete

> **Status:** Implemented  
> **Next Phase:** PHASE 5 — Admin Management APIs

---

## Summary

Cart and Order domain with Clean Architecture, order workflow states, and stock deduction/restoration logic.

---

## 1. Order Workflow States

| Status | Description |
|--------|-------------|
| `pending_review` | Order created, awaiting confirmation |
| `confirmed` | Order confirmed |
| `preparing` | Being prepared for shipment |
| `shipped` | Shipped |
| `delivered` | Delivered |
| `cancelled` | Cancelled (stock restored) |

**Enum:** `App\Domain\Order\Enums\OrderStatus`

---

## 2. Stock Deduction Logic

- **StockService** (`StockServiceInterface`):
  - `validateAndDeduct(array $items)` — validates stock, deducts on checkout
  - `restore(array $items)` — restores stock on order cancellation

- **Cart:** Validates stock on add/update (no deduction until checkout).
- **Checkout:** Runs in MongoDB transaction — deduct stock → create order → mark cart converted.

---

## 3. Cart Domain

- **CartStatus enum:** `active`, `converted`
- **CartRepositoryInterface** / **CartRepository**
- **CartServiceInterface** / **CartService** (uses CartRepository)

---

## 4. Order Domain

- **OrderRepositoryInterface** / **OrderRepository**
- **OrderServiceInterface** / **OrderService**
- **StockServiceInterface** / **StockService**

---

## 5. API Endpoints

**Customer:**
- `GET /api/v1/customers/cart` — show cart with items
- `POST /api/v1/customers/cart/items` — add item
- `DELETE /api/v1/customers/cart/items/{bookId}` — remove item
- `PATCH /api/v1/customers/cart/items/{bookId}` — update quantity
- `POST /api/v1/customers/orders/checkout` — checkout
- `GET /api/v1/customers/orders` — list orders
- `GET /api/v1/customers/orders/{id}` — show order
- `PATCH /api/v1/customers/orders/{id}/status` — cancel (status=cancelled only)

**Employee:**
- `GET /api/v1/employees/orders` — list orders (filter: assigned_to_me)
- `GET /api/v1/employees/orders/{id}` — show order
- `PATCH /api/v1/employees/orders/{id}/status` — update status

**Admin:**
- `GET /api/v1/admin/orders` — list (filters: search, status, employee_id, unassigned)
- `GET /api/v1/admin/orders/{id}` — show order
- `PATCH /api/v1/admin/orders/{id}/status` — update status
- `POST /api/v1/admin/orders/{id}/assign` — assign to employee

---

## 6. MongoDB Indexes

**Migration:** `2025_02_12_000002_add_orders_indexes.php` — `created_at` on orders

---

**PHASE 4 COMPLETE — Proceed to PHASE 5 when ready.**
