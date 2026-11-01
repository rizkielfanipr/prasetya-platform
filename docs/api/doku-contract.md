# DOKU contract as implemented

Sources, read 2026-11-01 from `developers.doku.com`: Backend Integration (Checkout), Signature Component from Request Header, Notification Best Practice, Retry Notification, Check Status API non-SNAP, HTTP Notification Sample non-SNAP, Expired Notification.

**Status: implemented against documentation. Never run against DOKU, because no sandbox credentials exist yet.** The sandbox checklist below is what remains.

## Outbound

| | Create checkout | Check status |
|---|---|---|
| Method and path | `POST /checkout/v1/payment` | `GET /orders/v1/status/{invoice}` |
| Sandbox | `https://api-sandbox.doku.com` | same |
| Production | `https://api.doku.com` | same |
| Signature includes Digest | Yes | No, GET has none |

Headers: `Client-Id`, `Request-Id`, `Request-Timestamp` (UTC, `2020-08-11T08:45:42Z`), `Signature`.

Signature:

```text
Client-Id:{id}\nRequest-Id:{id}\nRequest-Timestamp:{ts}\nRequest-Target:{path}\nDigest:{base64(sha256(rawBody))}
```

`Signature: HMACSHA256=` + base64(HMAC-SHA256(components, secret)), with no trailing newline.

Checkout fields sent: `order.amount`, `order.invoice_number`, `order.currency`, `order.callback_url`, `order.auto_redirect`, `order.line_items[]`, `payment.payment_due_date`, `customer.name`, `customer.email`, `customer.phone`.

Fields read: `response.payment.url`, `response.payment.token_id`, `response.payment.expired_date` (`yyyyMMddHHmmss`, documented as UTC+7).

## Inbound notification

`POST /api/v1/webhooks/doku`. Configure this URL in DOKU Back Office. `DOKU_NOTIFICATION_PATH` must equal its path, because that path is the `Request-Target` of the signature; it is read from config and not from the incoming request.

Checks, in order: all headers present, credentials configured (an empty secret can never validate), `Client-Id` matches, signature over the raw body, optional age, JSON and invoice present, duplicate, invoice known, amount matches, transition allowed.

## Status mapping

| `transaction.status` | Maps to | Effect |
|---|---|---|
| `SUCCESS` | Success | `PAID` if amount matches and transition is allowed |
| `PENDING` | Pending | `CREATED` to `PENDING` only |
| `EXPIRED` | Expired | Open payment to `EXPIRED`; ignored once paid |
| `FAILED` | Failed | Ignored on Checkout, as DOKU requires |
| `TIMEOUT`, `REDIRECT` | Not final | Ignored |
| `REFUNDED`, anything else | Unsupported | Stored, shown in reconciliation, no state change |

Retries from DOKU are at 30 minutes, 6 hours, and 12 hours when no 2xx arrives. Manual resend is in DOKU Back Office under Integration, HTTP Notification.

DOKU advises calling Check Status at least 60 seconds after payment completion. The back-office check is manual, so this is a note for the operator rather than something enforced.

## Sandbox checklist

Needs `DOKU_CLIENT_ID` and `DOKU_SECRET_KEY`, `PAYMENT_GATEWAY=doku`, and a public HTTPS URL for notifications.

- [ ] Checkout creation succeeds and `response.payment.url` opens.
- [ ] A real signed notification passes verification. This is the most important check: it proves the signature and `Request-Target` assumptions.
- [ ] Exact Checkout notification payload: the documentation samples are per channel, not Checkout-specific. Confirm `order.amount`, `channel.id`, `transaction.date`, and `transaction.original_request_id` are present.
- [ ] `order.amount` type and format in a real notification.
- [ ] Whether a retry keeps `Request-Id` and whether it is re-signed. Decides if the age check can be enabled.
- [ ] Expired Notification payload and signature. Its page shows only dashboard setup. It has its own URL field: point it at the same endpoint. Until confirmed, the scheduler is the source of expiry.
- [ ] A second attempt with a suffixed invoice number is accepted.
- [ ] `FAILED` then `SUCCESS` on one checkout.
- [ ] Check Status response shape for a Checkout order, including `order.amount`. Without the amount the check rejects a `SUCCESS` as a mismatch, which is safe but would need adapting. The newer `order.status` field is not used.
- [ ] `expired_date` timezone.
