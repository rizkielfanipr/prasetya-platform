# ADR-0007: Use Duitku Pop as the only payment gateway

- Status: Proposed
- Date: 2026-11-01
- Decision owners: Prasetya Group
- Related: [`../api/duitku-contract.md`](../api/duitku-contract.md), [ADR-0004](0004-doku-checkout-contract.md), [`../security/threat-model.md`](../security/threat-model.md)

## Context

The business has no legal entity and wants QRIS. DOKU's documentation contradicts itself on whether a Personal account can use QRIS, and QRIS is not available in DOKU Sandbox. Duitku states that individual merchants can use QRIS, offers it in sandbox, and has a hosted payment page (Duitku Pop). The owner has created a Duitku sandbox project.

Duitku's callback signature is weaker than DOKU's. It covers `merchantCode + amount + merchantOrderId` and not `resultCode`, and it is the same for every callback of an order. A signature therefore proves which order a callback is about, not that the order was paid. Duitku's documentation itself suggests a transaction check when a callback arrives.

## Decision

1. **Duitku Pop through window redirection** is the production gateway, behind the existing `PaymentGateway` and `NotificationVerifier` contracts. `PAYMENT_GATEWAY=duitku` selects it.
2. **A callback alone cannot produce `PAID`.** A verifier can mark a notification as requiring confirmation. `ProcessGatewayNotification` then asks the gateway with a server-to-server check, outside any database transaction, and passes the gateway's answer to `ApplyPaymentEvidence`. The status and amount of the check decide; the callback only supplies the channel.
3. **An unconfirmed success is a processing failure.** The event is `FAILED`, the response is 500, and Duitku's retry runs the check again. A real payment whose check lags is recovered this way, and a forged one never reaches `PAID`.
4. **The check runs once per new success callback.** Duitku warns that repeated calls can block requests for about an hour. Failure callbacks and duplicates of a processed callback make no call, and nothing polls.
5. **Non-success results change nothing.** `resultCode` is unsigned, so a failure callback is stored as `IGNORED`. Status `02` (canceled) from the check is also ignored, and expiry stays with the platform's own clock and the scheduler. This keeps ADR-0004 point 1: a failed attempt does not close a payment.
6. **Callback source addresses are checked** against Duitku's published list, as defence in depth. `DUITKU_ENFORCE_CALLBACK_IP=false` turns this off if the list or a proxy makes it reject real callbacks. Signature and check remain the controls that matter, because `trustProxies('*')` makes the address spoofable until it is narrowed.
7. **`DUITKU_PAYMENT_METHOD` limits checkout to one method**, for QRIS only. Its value depends on the channel opened on the account, and empty shows every active method.
8. **Return URLs and the payment window are gateway-neutral** configuration: `PAYMENT_RETURN_URL_*` and `PAYMENT_DUE_MINUTES`.
9. **DOKU is removed.** The owner decided not to use it, so its adapter, webhook route, configuration, and contract document are gone, and simulated DOKU rows were deleted from the local database. The fake gateway now signs callbacks the way Duitku does. The code remains in git history.
10. **Rules from ADR-0004 that stay in force:** one path to `PAID` through `ApplyPaymentEvidence`; late money wins over expiry; each attempt has its own gateway invoice number; duplicates are detected by content; authentic but rejected deliveries are acknowledged with 200 and processing failures get 500; processing is synchronous; refunds are out of scope; only connection failures that never reached the gateway are retried.

## Alternatives considered

- **Keep DOKU, as primary or fallback.** Rejected: QRIS on a Personal account is unconfirmed, it cannot be tested in sandbox, and an unused adapter is code to maintain.
- **Trust the callback signature.** Rejected: anyone who obtains one signature for an order could change `resultCode` to `00`.
- **Duitku API V2 with a custom payment page.** Rejected: more screens to build and own, with no benefit for the MVP.
- **Duitku.js popup.** Rejected: the site is static and redirect needs no third-party script.

## Consequences

- Webhook handling makes one outbound call per new success callback, so a slow Duitku slows the callback response. Timeouts are bounded by `DUITKU_TIMEOUT_SECONDS`.
- An outage of the check endpoint delays `PAID`. After Duitku's 5 retries, use the reconciliation check or resend from Duitku's report menu.
- Duitku has no refund service. Refunds are manual transfers by the merchant, recorded as admin notes. Disbursement is limited to company merchants.
- Payouts are manual withdrawals from the Duitku dashboard, and QRIS settles at H+2.
- BCA virtual accounts and Indomaret are not available to individual merchants.

## Validation

`tests/Integration/DuitkuAdapterTest.php` and `tests/Integration/DuitkuCallbackTest.php`, against the documented contract with no network. Live behaviour remains unverified until the sandbox checklist in `duitku-contract.md` passes.
