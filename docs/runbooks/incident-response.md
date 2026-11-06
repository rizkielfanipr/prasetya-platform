# Incident response

First, find out whether money or customers are affected. Payment integrity comes before availability. If in doubt, stop new checkouts instead of guessing at state.

**Stop new checkouts:** `php artisan down` stops everything including the webhook, so Duitku's callback gets no 200. It retries up to 5 times and then emails a failure notice. Keep it short, and afterwards resend from the report menu of its dashboard. To stop only sales, disable packages in the database or take the order form down on the sites.

## Finding things

Every response has `X-Request-Id`, and that id is in logs, `webhook_events.request_id`, `payment_events.request_id`, and `audit_logs.request_id`.

```sh
grep '"request_id":"01J..."' storage/logs/operations-*.log
```

Operational events are JSON lines in `storage/logs/operations-YYYY-MM-DD.log`: `payments.notification`, `payments.checkout`, `api.request`, `payments.expire_stale`. Application errors are in `storage/logs/laravel-*.log`.

## Scenarios

**All notifications rejected with `INVALID_SIGNATURE`.** Payments are not being confirmed. Compare `DUITKU_API_KEY` and `DUITKU_MERCHANT_CODE` with the project in the Duitku dashboard. After `config:cache`, env changes need another `php artisan config:cache`. Once fixed, resend from Duitku; rejected rows do not block a resend, since they hold no duplicate key.

**`CLIENT_MISMATCH` burst.** Wrong `DUITKU_MERCHANT_CODE`, or mixed sandbox and production. Same fix.

**All callbacks rejected with `SOURCE_NOT_ALLOWED`.** A proxy hides the address, or Duitku's published list changed. Compare with [`duitku-contract.md`](../api/duitku-contract.md). Set `DUITKU_ENFORCE_CALLBACK_IP=false` to restore payments, since the signature and status check still protect them, then fix the cause.

**Burst of invalid signatures from unknown sources.** Probably probing, and nothing changes state. Rate limiting applies; block the source at the host if it continues.

**Checkouts failing with 502.** See `failure_code` on the attempt. `DUITKU_UNREACHABLE` means network or Duitku; check their status. `DUITKU_HTTP_4xx` means a request or credential problem; `DUITKU_NOT_CONFIGURED` means missing env. Customers can retry: each attempt has a new invoice number.

**Notifications return 500.** Rows show `FAILED`. Either processing failed, or Duitku's status check did not confirm the payment. Fix the cause. Duitku's retry completes them, and so does a resend. A burst that the check never confirms suggests forged callbacks.

**Suspected wrong `PAID`.** Stop fulfilment for that order. Compare with Duitku. Check the `webhook_events` row and `audit_logs`. If forged, treat the API key as compromised.

**API key compromised.** Rotate it in the Duitku dashboard, update `.env`, run `php artisan config:cache`. Review `PROCESSED` events since the suspected time against Duitku's report. Also rotate `APP_KEY` if the server was accessed, noting that this invalidates sessions and encrypted data.

**Admin account compromised.** Set `is_active = 0` on the user: sessions end on their next request. Reset the password, and review `audit_logs` by actor.

**Database lost.** Restore per [`deployment.md`](deployment.md), then replay callbacks from Duitku.

## Failed jobs

Pengaturan shows the failed job count. `php artisan queue:failed` lists them and `php artisan queue:retry all` retries. Notification failure must never change payment state; no notification jobs exist yet.

## Afterwards

Record what happened, customer and financial impact, and the follow-up, referencing request ids. Personal data stays out of the write-up.
