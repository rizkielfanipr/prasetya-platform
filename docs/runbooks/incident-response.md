# Incident response

First, find out whether money or customers are affected. Payment integrity comes before availability. If in doubt, stop new checkouts instead of guessing at state.

**Stop new checkouts:** `php artisan down` stops everything including the webhook, so DOKU will retry at 30 minutes, 6 hours, and 12 hours. Keep it short, and after 12 hours resend from DOKU Back Office. To stop only sales, disable packages in the database or take the order form down on the sites.

## Finding things

Every response has `X-Request-Id`, and that id is in logs, `webhook_events.request_id`, `payment_events.request_id`, and `audit_logs.request_id`.

```sh
grep '"request_id":"01J..."' storage/logs/operations-*.log
```

Operational events are JSON lines in `storage/logs/operations-YYYY-MM-DD.log`: `payments.notification`, `payments.checkout`, `api.request`, `payments.expire_stale`. Application errors are in `storage/logs/laravel-*.log`.

## Scenarios

**All notifications rejected with `INVALID_SIGNATURE`.** Payments are not being confirmed. Compare `DOKU_SECRET_KEY` and `DOKU_NOTIFICATION_PATH` with DOKU Back Office and the configured URL. After `config:cache`, env changes need another `php artisan config:cache`. Once fixed, resend from DOKU; rejected rows do not block a resend, since they hold no duplicate key.

**`CLIENT_MISMATCH` burst.** Wrong `DOKU_CLIENT_ID`, or mixed sandbox and production. Same fix.

**Burst of invalid signatures from unknown sources.** Probably probing, and nothing changes state. Rate limiting applies; block the source at the host if it continues.

**Checkouts failing with 502.** See `failure_code` on the attempt. `DOKU_UNREACHABLE` means network or DOKU; check their status. `DOKU_HTTP_4xx` means a request or credential problem; `DOKU_NOT_CONFIGURED` means missing env. Customers can retry: each attempt has a new invoice number.

**Notifications return 500.** Rows show `FAILED`. Fix the cause. DOKU's retry completes them, and so does a resend.

**Suspected wrong `PAID`.** Stop fulfilment for that order. Compare with DOKU. Check the `webhook_events` row and `audit_logs`. If forged, treat the secret as compromised.

**Secret compromised.** Rotate in DOKU Back Office, update `.env`, run `php artisan config:cache`. Review `PROCESSED` events since the suspected time against DOKU's report. Also rotate `APP_KEY` if the server was accessed, noting that this invalidates sessions and encrypted data.

**Admin account compromised.** Set `is_active = 0` on the user: sessions end on their next request. Reset the password, and review `audit_logs` by actor.

**Database lost.** Restore per [`deployment.md`](deployment.md), then replay notifications from DOKU.

## Failed jobs

Pengaturan shows the failed job count. `php artisan queue:failed` lists them and `php artisan queue:retry all` retries. Notification failure must never change payment state; no notification jobs exist yet.

## Afterwards

Record what happened, customer and financial impact, and the follow-up, referencing request ids. Personal data stays out of the write-up.
