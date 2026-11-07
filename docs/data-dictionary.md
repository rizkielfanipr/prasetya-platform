# Data dictionary

MySQL 8. All timestamps UTC. Money is unsigned integer rupiah. `public_id` is a ULID; numeric ids never leave the server.

## Tables

| Table | Purpose | Key rules |
|---|---|---|
| `applications` | Brands: `WEBWORKS`, `SATAMU` | Unique `code`, `invoice_prefix` |
| `users` | Back-office accounts | `username` unique, lower-case, fixed at creation, used to sign in ([ADR-0009](decisions/0009-single-access-users-username-login.md)). `email` is nullable, hidden, and unused. No roles: every active user has full access. Inactive users cannot sign in. There is no `password_reset_tokens` table: passwords are set by another user or `platform:reset-password` |
| `customers` | Customer per brand | Unique (`application_id`, `email`). Never overwritten by public input |
| `packages` | Authoritative catalogue | Unique stable `code`, `price_amount`. `is_active` is catalogue state: set once when the seeder creates a package (Satamu starts `false`) and changed afterwards only by `SetPackageAvailability`, which writes an audit entry. Inactive packages cannot be ordered; their existing orders and payments are unaffected |
| `invoice_sequences` | Per-brand, per-month counter | Unique (`application_id`, `period`), row-locked |
| `orders` | One purchase | Unique `invoice_number`, own `status`, snapshot of customer contact |
| `order_items` | Package snapshot | Code, name, and price copied at order time |
| `order_status_histories` | Append-only order transitions | Source, actor, reason, request id |
| `admin_notes` | Internal notes | Writing one is audited |
| `webworks_order_details` | Template, business, domain, notes | One per order |
| `invitation_order_details` | Theme, event type and date, notes | One per order |
| `payments` | One checkout attempt | Unique (`order_id`, `attempt_number`), `gateway_invoice_number`, `gateway_request_id`, `gateway_reference` |
| `payment_events` | Append-only timeline per attempt | Unique (`webhook_event_id`, `type`) |
| `webhook_events` | Evidence of each delivery | `primary_dedupe_key` unique on first delivery only |
| `idempotency_keys` | Public mutation guard | Unique (`scope`, `idempotency_key`); stores a fingerprint and a resource reference |
| `audit_logs` | Append-only record of sensitive actions | Actor, action, entity, previous and new state, reason, request id. User changes: `user.created`, `user.updated`, `user.activated`, `user.deactivated`, `user.password_reset`, never with a password. Shown by entity ([ADR-0010](decisions/0010-consolidated-transaction-workspace.md), `AuditTrail`): order and payment on the transaction detail; any entry about a user on that user's page; everything else, including sign-in, profile, and packages, under Pengaturan → Aktivitas Sistem, which can also search every entry |
| `sessions`, `password_reset_tokens`, `cache`, `jobs`, `failed_jobs` | Laravel infrastructure | |

Append-only tables refuse updates and deletes through their models. This is not a database-level guarantee: someone with SQL access can still change them.

## Order state machine

```text
DRAFT -> PENDING_PAYMENT -> CONFIRMED
  |            |  |            ^
  v            |  v            |
CANCELLED <----+  EXPIRED -----+  (late verified payment)
```

The API creates orders directly in `PENDING_PAYMENT`. `DRAFT` is reserved. `CONFIRMED` and `CANCELLED` are final.

## Payment state machine

```text
CREATED -> PENDING -> PAID
   |          |        ^
   |          +-> FAILED ----+
   |          +-> EXPIRED ---+  (late verified payment)
   |          +-> CANCELLED
   +-> PAID, FAILED, EXPIRED, CANCELLED
```

`PAID` and `CANCELLED` are final. `FAILED` means a checkout could not be created, not that a customer's attempt was declined ([ADR-0007](decisions/0007-duitku-gateway.md)). Nothing in this release moves a payment to `CANCELLED`.

## Webhook processing states

| State | Meaning |
|---|---|
| `VERIFIED` | Authentic, being processed. Remains only if the process died mid-way |
| `PROCESSED` | Caused a state change |
| `IGNORED` | Authentic, deliberately no change |
| `DUPLICATE` | Same event as an earlier delivery, linked by `duplicate_of_id` |
| `REJECTED` | See `rejection_reason`: `SOURCE_NOT_ALLOWED`, `MISSING_HEADERS`, `INVALID_SIGNATURE`, `CLIENT_MISMATCH`, `MALFORMED_PAYLOAD`, `UNKNOWN_INVOICE`, `AMOUNT_MISMATCH`, `INVALID_TRANSITION`, `UNSUPPORTED_STATUS` |
| `FAILED` | Exception during processing; 500 sent so Duitku retries |

`RECEIVED` exists in the enum for the documented model, and is not currently written.

## Sensitive data

`webhook_events.sanitized_payload` holds an allow-list only: `service.id`, `acquirer.id`, `channel.id`, `order.invoice_number`, `order.amount`, `transaction.status`, `.type`, `.date`, `.original_request_id`. Signatures and raw bodies are never stored; `payload_digest` is a SHA-256 of the body. For a failed verification nothing from the body is kept except that digest.

Customer name, email, and phone exist on `customers` and `orders`. There is no automated retention or erasure yet.

## Definitions used on the dashboard

| Figure | Definition |
|---|---|
| Total paid, successful | `PAID` payments with `paid_at` in range |
| Pending | `CREATED` or `PENDING` payments created in range |
| Failed or expired | `FAILED` or `EXPIRED` payments created in range |
| Active orders | `PENDING_PAYMENT` or `CONFIRMED` orders created in range. Fulfilment does not exist yet, so a confirmed order stays active |
