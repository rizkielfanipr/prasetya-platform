# Deployment

Status: Draft. These steps have not yet been executed on the target cPanel host, so check PHP version, cron, and SSH availability there first.

## Requirements

PHP 8.3 or newer with `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `intl`; MySQL 8; HTTPS; document root pointing at `public/`. Build assets on CI or locally if the host has no Node.

## First deploy

```sh
composer install --no-dev --optimize-autoloader
npm ci --ignore-scripts && npm run build   # or upload public/build
cp .env.example .env                       # then edit, see below
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force                # applications, packages, first admin
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Production environment

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://platform.prasetyawebworks.web.id
SESSION_SECURE_COOKIE=true
PAYMENT_GATEWAY=duitku
DUITKU_ENVIRONMENT=production
DUITKU_MERCHANT_CODE=...
DUITKU_API_KEY=...
CORS_ALLOWED_ORIGINS=https://prasetyawebworks.web.id,https://satamu.prasetyawebworks.web.id
ADMIN_EMAIL=...
ADMIN_PASSWORD=...                         # remove after first seed
```

Change `trustProxies(at: '*')` in `bootstrap/app.php` to the real proxy addresses before going live, or remove it if there is none.

The demo seeder refuses to run here. Satamu packages are created inactive and re-seeding never changes availability; activate them only with `php artisan platform:package-activate <code> --reason=...` after the prices are approved.

## Cron

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Schedules `platform:expire-stale` every five minutes and `platform:prune-idempotency` daily. No queue worker is needed in this release: password reset mail is sent inline, and webhook processing is synchronous.

## Duitku

The callback address is sent on each invoice: `APP_URL` plus `/api/v1/webhooks/duitku`, or `DUITKU_CALLBACK_URL`. It must be public HTTPS on port 443. Follow [`duitku-contract.md`](../api/duitku-contract.md).

## Verify

```sh
curl -s https://platform.prasetyawebworks.web.id/api/v1/health
```

Then sign in, and on Pengaturan confirm the gateway shows credentials installed and the environment is right. Do not run a real transaction without an explicit decision by the owner: see M12.

## Update and rollback

```sh
php artisan down
# deploy code and built assets
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Roll back by redeploying the previous release. Do not run `migrate:rollback` on production without a fresh backup: it can destroy payment evidence. Prefer a forward fix.

## Backup and restore

```sh
mysqldump --single-transaction --routines prasetya_platform | gzip > backup-$(date +%F).sql.gz
gunzip -c backup-YYYY-MM-DD.sql.gz | mysql prasetya_platform_restore_test
```

Daily, kept off the server. **Restoration has not been proven yet.** M11 requires a restore into a scratch database, followed by checking counts of `payments`, `webhook_events`, and `audit_logs` and opening one transaction.

After restoring production, payments confirmed since the backup are missing. Use Duitku's report and manual callback resend to replay them: duplicates are safe.
