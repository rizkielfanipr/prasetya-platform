# ADR-0004: How the platform applies the DOKU Checkout contract

- Status: Superseded by ADR-0007
- Date: 2026-11-01
- Decision owners: Prasetya Group
- Related: [ADR-0007](0007-duitku-gateway.md), [`../security/threat-model.md`](../security/threat-model.md)

## Context

DOKU's documentation settles the request shape and signature, and leaves some behaviour open. It also contains one rule that overrides the brief's assumptions: a Checkout integration "must ignore" `transaction.status` `FAILED`, because the customer can switch payment method after a failure. Sandbox credentials are not available yet, so open points cannot be tested.

## Decision

1. **`FAILED` notifications change nothing.** They are stored as `IGNORED`. `PaymentStatus::FAILED` is reached only when a checkout cannot be created.
2. **One path to `PAID`.** Notifications and admin-triggered status checks both go through `ApplyPaymentEvidence`, which requires a matching amount and an allowed transition.
3. **Late money wins.** `SUCCESS` after `EXPIRED` or `FAILED` moves the payment to `PAID`, and an expired order to `CONFIRMED`. A paid payment on a cancelled order stays visible in reconciliation and is not repaired.
4. **Each attempt has its own gateway invoice number:** `PW-202611-000128`, then `PW-202611-000128-2`. DOKU does not document reusing an invoice number, and this maps every notification to exactly one attempt. Repeat clicks reuse the open attempt rather than creating another.
5. **Duplicates are detected by content**, as a hash of invoice, amount, status, date, and original request id. DOKU does not document whether a retry keeps its `Request-Id`.
6. **The timestamp age check is off by default.** DOKU retries for up to 12 hours and allows manual resend from its back office, and does not say whether retries are re-signed. A strict window could reject real payments. Replays are safe anyway because `PAID` is final and duplicates have no side effect. It can be enabled with `DOKU_NOTIFICATION_MAX_AGE_SECONDS`.
7. **Acknowledgement policy.** Invalid signature: 401. Authentic but rejected (unknown invoice, amount mismatch, invalid transition): 200, since evidence is stored and a retry cannot change the answer. Processing failure: 500, so DOKU's retry can complete it.
8. **Processing is synchronous inside the request.** It is local database work with no network call, which fits a cPanel environment without a long-running worker. DOKU's advice to acknowledge quickly is met by keeping it short.
9. **Refunds are out of scope.** `REFUNDED` is stored as `REJECTED / UNSUPPORTED_STATUS` and shown in reconciliation; the payment stays `PAID`.
10. **One merchant account for both brands**, pending the open decision. A second account would need per-brand credentials in `DokuConfig`.
11. **Retries.** Only connection failures that never reached DOKU (cURL 6 and 7) are retried. A timeout may have reached DOKU, so the attempt is marked failed and a new attempt uses a new invoice number.

## Alternatives considered

- **Treat `FAILED` as terminal.** Rejected: contradicts DOKU and would mark recoverable payments as failed.
- **Queue notification processing.** Rejected for now: adds a worker dependency without solving a measured problem.
- **Reuse the invoice number across attempts.** Rejected: undocumented behaviour and ambiguous notification mapping.

## Consequences

- The "failed" dashboard figure counts checkout creation failures, not declined customer attempts.
- An invalid-signature flood writes rows; it is bounded by the webhook rate limit and body size cap.
- Points 4 to 6 must be confirmed in sandbox: the contract document was removed with the adapter.

## Validation

`tests/Integration/WebhookProcessingTest.php` and `DokuAdapterTest.php`. Live behaviour remains unverified until sandbox credentials exist.
