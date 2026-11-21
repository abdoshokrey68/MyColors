# MyColors — E-Commerce Order & Promotion API

Laravel 12 REST API assessment: products, cart, promotions, checkout, and orders — built with a modular architecture, repository + service layers, and concurrency-safe inventory.

## Requirements

- PHP 8.2+
- Composer
- MySQL 8+ (or SQLite for quick local/tests)
- Redis (optional, recommended for product-list caching)
- Docker Desktop (optional, for Sail)

## Quick start (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mycolors
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=database
# Prefer Redis when available:
# CACHE_STORE=redis
# REDIS_HOST=127.0.0.1
```

Then:

```bash
php artisan migrate --seed
php artisan serve
php artisan queue:work   # processes OrderPlaced listener
```

API base: `http://127.0.0.1:8000/api`

### Seeded accounts

| Role     | Email                 | Password  |
|----------|-----------------------|-----------|
| Customer | `customer@example.com`| `password`|
| Admin    | `admin@example.com`   | `password`|

Seeded coupons: `SUMMER20` (20% off, min $100, max discount $50), `FLAT15` ($15 off, min $50).

## Docker / Sail

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Sail-oriented `.env` values:

```env
APP_PORT=80
DB_HOST=mysql
DB_USERNAME=sail
DB_PASSWORD=password
REDIS_HOST=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
WWWGROUP=1000
WWWUSER=1000
```

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail artisan queue:work
```

On Windows PowerShell use `vendor\bin\sail` or `php vendor/bin/sail`.

## Architecture

```
Route → FormRequest → Controller → Service → Repository → Model
                              ↓
                     API Resource / JsonResponse
```

Domain code lives in modules:

| Module      | Responsibility                          |
|-------------|-----------------------------------------|
| `Auth`      | Register / login / logout / me (Sanctum)|
| `Product`   | Catalog + admin CRUD + list caching     |
| `Cart`      | Authenticated cart + stock checks       |
| `Promotion` | Coupon rules + admin CRUD               |
| `Order`     | Checkout, orders, cancel, idempotency   |

Shared HTTP helpers stay in `App\Http\Controllers\Api\BaseController`. Controllers stay thin; business rules and transactions live in services; persistence stays in repositories.

## API overview

Auth header for protected routes: `Authorization: Bearer {token}` (token returned by `/api/auth/login` or `/api/auth/register`). Send `Accept: application/json`.

### Response format

Every endpoint returns the same envelope:

```json
{ "success": true, "message": "Order placed successfully.", "data": { } }
```

Errors return `{ "success": false, "message": "..." }`. Validation errors also include an `errors` object keyed by field. Paginated lists return `data.items` and `data.meta` (`current_page`, `per_page`, `total`, `last_page`).

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| POST | `/api/auth/register` | — | Create customer |
| POST | `/api/auth/login` | — | Returns token |
| POST | `/api/auth/logout` | user | |
| GET | `/api/auth/me` | user | |
| GET | `/api/products` | — | Search, filters, sort, pagination |
| GET | `/api/products/{id}` | — | Active products only |
| GET/POST/PATCH/DELETE | `/api/cart…` | user | Cart CRUD |
| POST | `/api/promotions/validate` | user | Eligibility preview |
| POST | `/api/checkout` | user | Optional `Idempotency-Key`; throttle 10/min |
| GET | `/api/orders` | user | `per_page` max 100 |
| GET | `/api/orders/{id}` | user | |
| POST | `/api/orders/{id}/cancel` | user | Idempotent cancel; releases coupon |
| CRUD | `/api/admin/products` | admin | Delete deactivates (no hard delete) |
| CRUD | `/api/admin/promotions` | admin | |
| PATCH | `/api/admin/orders/{id}/status` | admin | Enforced status transitions |

Full request/response examples: [`docs/MyColors.postman_collection.json`](docs/MyColors.postman_collection.json).

### Product listing query params

- `search` — name contains
- `min_price` / `max_price`
- `available` — `1` = in stock only, `0` = out of stock only (omit for all)
- `sort_by` — `price`, `name`, `created_at`, `stock_quantity` (default `created_at`)
- `sort_dir` — `asc` / `desc` (default `desc`)
- `per_page` (1–100, default 15), `page`

Example: `GET /api/products?search=shirt&min_price=10&max_price=100&available=1&sort_by=price&sort_dir=asc&per_page=10`

### Request bodies

| Endpoint | Body |
|----------|------|
| `POST /api/auth/register` | `{ "name", "email", "password", "password_confirmation" }` |
| `POST /api/auth/login` | `{ "email", "password" }` |
| `POST /api/cart/items` | `{ "product_id": 1, "quantity": 2 }` (adding an existing product merges quantities) |
| `PATCH /api/cart/items/{id}` | `{ "quantity": 3 }` |
| `POST /api/promotions/validate` | `{ "code": "SUMMER20" }` — validates against the current cart subtotal |
| `POST /api/checkout` | `{ "promotion_code": "SUMMER20" }` (optional). Optional header `Idempotency-Key: <unique-client-key>` |
| `PATCH /api/admin/orders/{id}/status` | `{ "status": "paid" }` |

The checkout body intentionally accepts **no prices, quantities or totals**: everything is read from the cart and recalculated on the server.

### Typical flow

1. `POST /api/auth/login` with `customer@example.com` / `password` → copy `token`.
2. `GET /api/products` → pick a product ID.
3. `POST /api/cart/items` → `GET /api/cart` to see the subtotal.
4. `POST /api/promotions/validate` with `SUMMER20` (cart must be ≥ $100).
5. `POST /api/checkout` → order is created, stock is reduced, cart is cleared.
6. `GET /api/orders` / `GET /api/orders/{id}` → order with item snapshots.
7. `POST /api/orders/{id}/cancel` → stock restored, coupon released; repeat the call to see it is idempotent.

### Order statuses

`pending`, `placed` (default on checkout), `paid`, `shipped`, `delivered`, `cancelled`.

Cancellable: `pending` / `placed` / `paid`. Once `shipped` or `delivered`, cancel returns `422`.

There is no payment gateway in scope, so `paid`, `shipped` and `delivered` are set manually through `PATCH /api/admin/orders/{id}/status`. Allowed transitions (validated by `OrderStatus::canTransitionTo()`):

```text
placed/pending → paid → shipped → delivered
placed/pending/paid → cancelled
```

## Concurrency handling

**TL;DR — stock 5, customer A buys 4, customer B buys 3 at the same time:** checkout locks the product rows with `SELECT … FOR UPDATE` inside a DB transaction. One transaction gets the lock and commits (stock → 1). The other waits, then re-checks stock against the committed value (1 < 3) and fails with `422 Insufficient stock`, so nothing is written. As a last line of defense, `stock_quantity` is `UNSIGNED`, so the database itself rejects negative stock. The same locking (on the cart row) stops one customer from creating two orders by double-clicking checkout.

Details:

Checkout runs inside a DB transaction with retry (`DB::transaction(..., 5)`). Lock order is always:

1. **Cart row + cart items** — `SELECT … FOR UPDATE` on the cart, then a **locking** load of `items` (`items()->with('product')->lockForUpdate()->get()`). Under InnoDB `REPEATABLE READ`, the first plain `SELECT` in a transaction establishes a consistent-read snapshot; a later non-locking `fresh()`/`load()` can still return pre-clear cart items even after another transaction committed. Locking reads are required so the second checkout sees the emptied cart.
2. **Products** — `lockForUpdate` on involved product IDs (sorted) for stock safety.
3. **Promotion** — `lockByCode` when a coupon is applied; per-customer usage counts use `lockForUpdate` during checkout; `used_count` increments atomically with `UPDATE … WHERE usage_limit IS NULL OR used_count < usage_limit`.

Then:

4. Recalculate money in **integer cents** on the server (never trust client totals).
5. Create order + order items (price/name/SKU snapshots).
6. Decrement stock, record promotion usage, clear cart.
7. Dispatch `OrderPlaced` (`ShouldQueueAfterCommit`) so the listener never runs before commit.
8. Bust product-list cache via `DB::afterCommit` + `Cache::increment` on the version key.

**Idempotency:** the `idempotency_keys` row is reserved as `processing` in the **same** transaction before checkout work. The unique `(user_id, key)` index serializes duplicates. On success the row is updated to `completed` with the response payload. On failure the transaction rolls back and the key is released for retry. A still-`processing` key returns `409`.

If two customers race for limited stock, one transaction waits on the row lock; the second re-checks stock and fails with insufficient stock instead of going negative.

**SQLite note:** feature tests are sequential on SQLite because `lockForUpdate` is not equivalent to InnoDB row locks. For a real overlapping-transaction proof on MySQL (no HTTP server — `artisan serve` is single-threaded):

```bash
php artisan demo:concurrent-checkout              # two users, qty 4+3 vs stock 5 → stock 1, 1 order
php artisan demo:concurrent-checkout --mode=same-user  # same user twice in parallel → 1 order
```

Workers are spawned with `Process::concurrently` as `php artisan demo:checkout-worker {userId} --start-at=…` so both transactions start together on separate DB connections.

> The demo requires MySQL (`DB_CONNECTION=mysql`) and creates its own temporary product and users in the current database. Run it on a local/dev database, then `php artisan migrate:fresh --seed` to reset if needed. The command exits non-zero if the expected stock or order count does not match.

## Caching (bonus)

Product listing uses a cache-aside key with a version counter (`products:list:version`). Mutations bump the version with `Cache::increment` **after commit** so rolled-back checkouts do not invalidate caches. Repositories do not know about caching.

Set `CACHE_STORE=redis` when Redis is available. Tests use `array` cache.

## Queues & events (bonus)

`OrderPlaced` is handled by `ProcessOrderPlaced` (`ShouldQueueAfterCommit`). With `QUEUE_CONNECTION=database|redis`, run a worker. Tests use `sync`.

## Idempotency (bonus)

Send `Idempotency-Key` on checkout. Same user + key + payload replays the stored JSON response. Same key with a different payload returns `409`. In-progress keys return `409` with a dedicated message. Keys are unique per user.

## Rate limiting (bonus)

- Global API: `60` requests/minute per user or IP (`throttle:api`)
- Checkout: `10` requests/minute (`throttle:checkout`)

## Coupon release on cancel

Cancelling a `pending`/`placed`/`paid` order restores stock **and** releases the coupon: `used_count` is decremented (never below 0) and the matching `promotion_usages` row for that `order_id` is deleted, all inside the same transaction as the stock restore. This keeps usage limits accurate, so a cancelled order does not consume a customer's per-customer coupon quota.

Cancellation is safe to repeat: the order row is locked with `FOR UPDATE`, and an already-cancelled order is returned as-is (`200`) without restoring stock or releasing the coupon a second time.

## Error handling

| Exception / case | HTTP | Message (typical) |
|------------------|------|-------------------|
| Validation | 422 | Laravel validation errors |
| Empty cart | 422 | Empty cart. |
| Insufficient stock | 422 | Insufficient stock… |
| Invalid / expired coupon | 422 | … |
| Coupon usage limit | 422 | … |
| Invalid order status / transition | 422 | … |
| Product not found / inactive | 404 | Product not found. |
| Cart item not found (or another user's item) | 404 | … |
| Order not found **or other user's order** | 404 | Order not found. (404 on purpose — avoids ID enumeration; no 403) |
| Admin gate | 403 | Admin access required. |
| Idempotency conflict / in progress | 409 | … |
| Unauthenticated | 401 | Unauthenticated. |
| Rate limit exceeded | 429 | Too Many Attempts. |

## Database indexes

| Table / index | Why |
|---------------|-----|
| `products.sku` unique | Natural business key |
| `products(name)` | Search |
| `products(status, price)` | Active listing + price filters/sort |
| `products(stock_quantity)` | Availability filter |
| `promotions.code` unique | Lookup by coupon code |
| `promotions(is_active, starts_at, ends_at)` | Eligibility window |
| `promotion_usages(promotion_id, user_id)` | Per-customer usage counts |
| `carts.user_id` unique | One cart per customer |
| `cart_items(cart_id, product_id)` unique | Merge quantities safely |
| `orders(user_id, status)` | Customer order history / filters |
| `idempotency_keys(user_id, key)` unique | Serializes concurrent idempotent checkouts |

## Trade-offs

| Decision | Trade-off |
|----------|-----------|
| Modular packages (`nwidart`) | Clear boundaries; slightly more boilerplate than a flat `app/` tree |
| Repository + Service | Testable domain rules; more layers for small endpoints |
| Lock order cart → products → promotion | Correct under concurrency; hot carts/SKUs serialize |
| Idempotency reserve in same TX | True atomicity; “in progress” is mainly visible across connections / manual rows |
| Cache after commit + increment | Safe with rollbacks; relies on atomic increment support of the cache store |
| Soft-deactivate products on delete | Avoids FK 500s with cart_items; catalog retains history |
| Integer cents for money math | Avoids float drift; API still returns `"123.45"` strings |
| Admin via `is_admin` flag | Simple for assessment; RBAC would replace this in production |
| 404 for foreign orders | Hides existence; clients cannot distinguish missing vs forbidden |

## Testing

```bash
php artisan test --compact
```

Feature tests cover auth, products, cart, promotions, checkout concurrency scenarios, cancel, idempotency, admin gates, and money rounding. The SQLite suite runs checkouts **sequentially**; use `demo:concurrent-checkout` against MySQL for a parallel proof.

## Project layout

```
Modules/{Auth,Product,Cart,Promotion,Order}/
  app/Http/Controllers|Requests|Resources
  app/Models|Enums|Services|Repositories
  database/migrations|factories|seeders
  routes/api.php
  tests/Feature|Unit

app/
  Http/Controllers/Api/BaseController.php   # shared JSON envelope
  Http/Middleware/EnsureUserIsAdmin.php     # admin gate
  Support/Money.php                         # integer-cents money helper
  Console/Commands/                         # demo:concurrent-checkout, demo:checkout-worker
docs/
  MyColors.postman_collection.json
```
