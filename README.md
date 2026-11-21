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

Auth header for protected routes: `Authorization: Bearer {token}`

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
| GET | `/api/orders` | user | |
| GET | `/api/orders/{id}` | user | |
| POST | `/api/orders/{id}/cancel` | user | Idempotent cancel |
| CRUD | `/api/admin/products` | admin | |
| CRUD | `/api/admin/promotions` | admin | |

Full request/response examples: [`docs/MyColors.postman_collection.json`](docs/MyColors.postman_collection.json).

### Product listing query params

- `search` — name contains
- `min_price` / `max_price`
- `available` — `1` = in stock
- `sort_by` — `price`, `name`, `created_at`
- `sort_dir` — `asc` / `desc`
- `per_page`, `page`

### Checkout body

```json
{
  "promotion_code": "SUMMER20"
}
```

Optional header: `Idempotency-Key: <unique-client-key>`

## Concurrency handling

Checkout runs inside a DB transaction with retry (`DB::transaction(..., 5)`):

1. Load the user cart.
2. `SELECT … FOR UPDATE` on all involved product rows (sorted IDs to reduce deadlock risk).
3. Re-validate stock and product status against locked rows.
4. Recalculate subtotal / discount / total on the server (never trust client totals).
5. Create order + order items (price/name/SKU snapshots).
6. Decrement stock, record promotion usage, clear cart.
7. Dispatch `OrderPlaced` for async post-processing.

If two customers race for limited stock, one transaction waits on the row lock; the second re-checks stock and fails with insufficient stock instead of going negative.

**Note:** SQLite (used in some test setups) does not enforce `lockForUpdate` the same way as MySQL/InnoDB. Production inventory safety assumes MySQL (or another engine with proper row locking).

## Caching (bonus)

Product listing uses a cache-aside key with a version counter (`products:list:version`). Mutations (admin CRUD, checkout stock change, cancel restore) bump the version so stale filter/page keys expire naturally without `Cache::flush()`.

Set `CACHE_STORE=redis` when Redis is available. Tests use `array` cache.

## Queues & events (bonus)

`OrderPlaced` is handled by `ProcessOrderPlaced` (`ShouldQueue`). With `QUEUE_CONNECTION=database|redis`, run a worker. Tests use `sync`.

## Idempotency (bonus)

Send `Idempotency-Key` on checkout. Same user + key + payload replays the stored JSON response. Same key with a different payload returns `409`. Keys are unique per user.

## Rate limiting (bonus)

- Global API: `60` requests/minute per user or IP (`throttle:api`)
- Checkout: `10` requests/minute (`throttle:checkout`)

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
| `idempotency_keys(user_id, key)` unique | Idempotent checkout |

## Trade-offs

| Decision | Trade-off |
|----------|-----------|
| Modular packages (`nwidart`) | Clear boundaries; slightly more boilerplate than a flat `app/` tree |
| Repository + Service | Testable domain rules; more layers for small endpoints |
| Row locks on checkout | Correct stock under concurrency; can serialize hot SKUs |
| Cache versioning | Simple invalidation; old keys linger until TTL (acceptable) |
| Snapshot order items | Historical accuracy; product edits never rewrite past orders |
| Admin via `is_admin` flag | Simple for assessment; RBAC would replace this in production |
| Idempotency after success | Protects retries; concurrent first-time duplicates rely on unique constraint recovery |

## Testing

```bash
php artisan test --compact
```

Feature tests cover auth, products, cart, promotions, checkout concurrency scenarios, cancel, idempotency, and admin gates.

## Project layout

```
Modules/{Auth,Product,Cart,Promotion,Order}/
  app/Http/Controllers|Requests|Resources
  app/Models|Enums|Services|Repositories
  database/migrations|factories|seeders
  routes/api.php
  tests/Feature|Unit
```
