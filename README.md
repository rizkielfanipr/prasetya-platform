# Prasetya Platform

Laravel modular monolith that takes orders and monitors payments for Prasetya Webworks and Satamu Invitation: a versioned REST API, a Vue/Inertia back office, MySQL, and DOKU Checkout.

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

With `PAYMENT_GATEWAY=fake` nothing leaves your machine. Set `DOKU_CLIENT_ID` and `DOKU_SECRET_KEY` to any local values, then:

```sh
curl -s localhost:8000/api/v1/orders -H 'Content-Type: application/json' -H 'Idempotency-Key: try-1' \
  -d '{"application_code":"WEBWORKS","package_code":"PW-LANDING-STARTER","customer":{"name":"Coba","email":"coba@example.test"}}'
curl -s -X POST localhost:8000/api/v1/orders/REFERENCE/checkout -H 'Idempotency-Key: try-2'
php artisan doku:simulate-notification INVOICE 499000
```

The last command sends a correctly signed notification through the real webhook path. It refuses to run in production or with the real gateway.

## Real DOKU

Set `PAYMENT_GATEWAY=doku` with sandbox credentials and work through the checklist in [`docs/api/doku-contract.md`](docs/api/doku-contract.md). The integration has not yet run against DOKU.
# prasetya-platform
