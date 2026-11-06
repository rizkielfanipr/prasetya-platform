# Prasetya Platform

Laravel modular monolith that takes orders and monitors payments for Prasetya Webworks and Satamu Invitation: a versioned REST API, a Vue/Inertia back office, MySQL, and Duitku Pop.

Start with [`docs/README.md`](docs/README.md). What is done and what is blocked is in the [delivery status](docs/implementation-plan.md#delivery-status).

## Requirements

PHP 8.3+, Composer, Node 22+, MySQL 8.

## Install

```sh
composer install
npm ci --ignore-scripts
cp .env.example .env
php artisan key:generate
```

Create the databases:

```sql
CREATE DATABASE prasetya_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE prasetya_platform_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE prasetya_platform_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Set `DB_*` in `.env`. Tests assume `root` with no password on `127.0.0.1`; change `phpunit.xml` and `.env.e2e` if yours differs. Then:

```sh
php artisan migrate --seed
```

In `local`, seeding adds demo data and these accounts, password `demo-password-only-1`: `demo.admin@example.test`, `demo.finance@example.test`, `demo.ops@example.test`. They exist for Super Admin, Finance, and Operations. Demo data is refused in production, where the first admin comes from `ADMIN_EMAIL` and `ADMIN_PASSWORD`, or `php artisan platform:create-admin`.

## Commands

| Task | Command |
|---|---|
| Develop | `php artisan serve` and `npm run dev` |
| Migrate | `php artisan migrate` |
| Reset with seed data | `php artisan migrate:fresh --seed` |
| Format PHP | `vendor/bin/pint` |
| Format JS and CSS | `npm run format` |
| Check formatting | `vendor/bin/pint --test` and `npm run format:check` |
| Lint | `npm run lint` |
| Static analysis | `vendor/bin/phpstan analyse --memory-limit=1G` |
| Type check | `npm run typecheck` |
| Backend tests | `vendor/bin/pest` |
| Component tests | `npm test` |
| Browser tests | `npm run build`, then `npx playwright install chromium` once, then `npm run test:e2e` |
| Production build | `npm run build` |

## Trying a payment locally

With `PAYMENT_GATEWAY=fake` nothing leaves your machine and no credentials are needed:

```sh
curl -s localhost:8000/api/v1/orders -H 'Content-Type: application/json' -H 'Idempotency-Key: try-1' \
  -d '{"application_code":"WEBWORKS","package_code":"PW-LANDING-STARTER","customer":{"name":"Coba","email":"coba@example.test"}}'
curl -s -X POST localhost:8000/api/v1/orders/REFERENCE/checkout -H 'Idempotency-Key: try-2'
php artisan platform:simulate-notification INVOICE 499000
```

The last command sends a correctly signed callback through the real verification path. It refuses to run in production or with the real gateway.

## Trying checkout from prasetya-webworks

Without a gateway account, `PAYMENT_GATEWAY=fake` sends customers to a clearly marked simulated pay page at `/dev/fake-checkout/{invoice}`. It signs a notification and sends it through the real verification path, then returns to the site. The route does not exist in production or with the real gateway. In `.env`, set `PAYMENT_RETURN_URL_WEBWORKS=http://localhost:4321/order/status/`; see that repository's `.env.example` for its side.

## Real gateway

Set `PAYMENT_GATEWAY=duitku` with sandbox credentials and work through the checklist in [`docs/api/duitku-contract.md`](docs/api/duitku-contract.md). The integration has not yet run against Duitku.
# prasetya-platform
