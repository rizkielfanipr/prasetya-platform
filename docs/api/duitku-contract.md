# Duitku contract as implemented

| Field | Value |
|---|---|
| Status | Active, payment flow verified against live sandbox |
| Owner | Prasetya Group |
| Last updated | 2026-11-01 |
| Decision | [ADR-0007](../decisions/0007-duitku-gateway.md) |
| Sources | [Duitku Pop](https://docs.duitku.com/pop/id/), [API V2](https://docs.duitku.com/api/id/), [FAQ](https://www.duitku.com/faq/) |

Code: `app/Integrations/Duitku`.

## Setup

1. Create a project in the Duitku dashboard under **Project Saya > Tambah Project**.
2. Put its merchant code and API key in `.env`: `DUITKU_MERCHANT_CODE`, `DUITKU_API_KEY`. Never commit them or paste them into chat.
3. Set `PAYMENT_GATEWAY=duitku` and `DUITKU_ENVIRONMENT=sandbox`.
4. Set `DUITKU_CALLBACK_URL` to the public HTTPS address of `/api/v1/webhooks/duitku`, on port 443. Empty means `APP_URL` plus that path.
5. Optional, for QRIS only: set `DUITKU_PAYMENT_METHOD` to the code opened on the account (`SP`, `NQ`, or `SQ`).

Demo data is seeded only with `PAYMENT_GATEWAY=fake`, because it would otherwise call Duitku.

## Create invoice

`POST /api/merchant/createInvoice` with JSON, on `https://api-sandbox.duitku.com` or `https://api-prod.duitku.com`.

| Header | Value |
|---|---|
| `x-duitku-merchantcode` | Merchant code |
| `x-duitku-timestamp` | Unix time in milliseconds |
| `x-duitku-signature` | `hash_hmac('sha256', merchantCode . timestamp, apiKey)` |

Fields sent: `paymentAmount`, `merchantOrderId` (the attempt's gateway invoice number), `productDetails`, `email`, `phoneNumber`, `customerVaName` (20 characters), `itemDetails`, `customerDetail`, `callbackUrl` (server), `returnUrl` (browser), `expiryPeriod` (minutes), and `paymentMethod` when configured.

Fields read: `statusCode` (`00`), `paymentUrl` (must be HTTPS), `reference`. No expiry is returned, so the platform uses its own `PAYMENT_DUE_MINUTES`. Duitku keeps the same response for a repeated `merchantOrderId` for about 5 minutes, and every new attempt uses a new number.

## Callback

`POST /api/v1/webhooks/duitku` with `application/x-www-form-urlencoded`. Signature: `hash_hmac('sha256', merchantCode . amount . merchantOrderId, apiKey)`, over the raw received values.

Stored fields, allow-listed: `paymentCode`, `resultCode`, `merchantOrderId`, `amount`, `reference`, `issuerCode`, `settlementDate`. Names, card data, and everything else are dropped.

| Case | Stored as | Response |
|---|---|---|
| Source address not allowed | `REJECTED / SOURCE_NOT_ALLOWED` | 401 |
| Missing fields | `REJECTED / MISSING_HEADERS` | 401 |
| Wrong merchant | `REJECTED / CLIENT_MISMATCH` | 401 |
| Bad signature | `REJECTED / INVALID_SIGNATURE` | 401 |
| Unknown invoice | `REJECTED / UNKNOWN_INVOICE` | 200 |
| `resultCode` not `00` | `IGNORED`, no status check | 200 |
| `00`, check says paid and amount matches | `PROCESSED`, payment `PAID` | 200 |
| `00`, check says paid and amount differs | `REJECTED / AMOUNT_MISMATCH` | 200 |
| `00`, check says not paid or cannot be reached | `FAILED` | 500, so Duitku retries |
| Repeat of a finished callback | `DUPLICATE`, no status check | 200 |

Duitku retries until it gets a 200, up to 5 times, then emails a failure notice. Callbacks can be resent from the report menu in the dashboard.

Published callback addresses:

- Production: `182.23.85.8`, `182.23.85.9`, `182.23.85.10`, `182.23.85.13`, `182.23.85.14`, `103.177.101.184`, `103.177.101.185`, `103.177.101.186`, `103.177.101.189`, `103.177.101.190`
- Sandbox: `182.23.85.11`, `182.23.85.12`, `103.177.101.187`, `103.177.101.188`

## Check transaction

`POST /webapi/api/merchant/transactionStatus`, on `https://sandbox.duitku.com` or `https://passport.duitku.com`. Body: `merchantCode`, `merchantOrderId`, `signature` = `hash_hmac('sha256', merchantCode . merchantOrderId, apiKey)`.

| `statusCode` | Meaning | Platform |
|---|---|---|
| `00` | Success | `Success` |
| `01` | Pending | `Pending` |
| `02` | Canceled | `Failed`, ignored |
| Other | Unknown | `Unsupported`, shown in reconciliation |

Duitku warns against automatic repeated calls: the limit can block requests for about an hour.

## Where the documentation is unclear

| Point | Documentation says | Implemented as |
|---|---|---|
| Check transaction format | "x-www-form-urlencoded", but both samples send JSON | JSON |
| Failure code in callback | `01` in one table, `02` in another | Anything other than `00` is a non-success |
| Timestamp | "milliseconds, in our time zone (Jakarta)", but Unix time has no zone | Plain Unix milliseconds, as in the PHP sample |
| Unknown order on check | Not documented. Sandbox answers 404 `{"Message":"Transaction not found"}`, also for an invoice whose customer has not chosen a method yet | 404 means no record; other errors are gateway failures |
| `returnUrl` that already has a query string | Not documented | Ours has `?order=`, and Duitku adds its own parameters |
| Expiry on each channel | QRIS 10 minutes by default, up to 60. Some channels ignore the request | Requests `PAYMENT_DUE_MINUTES` (60) |

## Sandbox checklist

Checked items ran against the sandbox; the rest have not run live yet.

- [x] Create invoice is accepted and returns an HTTPS `paymentUrl` (2026-11-01). The response also has `amount`, which is not read.
- [ ] `returnUrl` with `?order=` returns to the status page with the order intact.
- [ ] A sandbox QRIS payment is shown on the hosted page. On 2026-11-01, `SP` and `NQ` were active. API V2 inquiry returned a `qrString` for `SP`, and for `NQ` returned HTTP 500 "Failed to generate QR String Nobu". The hosted page has not been confirmed to show a QR.
- [x] The callback arrives, passes signature and address checks, and the check returns `00` with the same amount (2026-11-01, `PW-202611-000005`, virtual account `BC`, through a Cloudflare tunnel).
- [x] The payment becomes `PAID` and the order `CONFIRMED` (2026-11-01).
- [ ] The status page on the site shows the paid order after the customer returns.
- [ ] A callback with a wrong signature gets 401 and changes nothing.
- [ ] A callback is not trusted when the check says pending.
- [ ] An abandoned payment: confirm its check status, whether a failure callback arrives, and which `resultCode` it has.
- [x] Check of an unknown order: 404 `{"Message":"Transaction not found"}` (2026-11-01).
- [ ] Confirm whether an expired QR matches the requested `expiryPeriod`.
