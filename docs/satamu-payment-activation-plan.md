# Satamu payment activation plan

| Field | Value |
|---|---|
| Status | Draft for approval |
| Owner | Prasetya Group |
| Last updated | 2026-11-06 |
| Decision | [ADR-0008](decisions/0008-satamu-payment-activation.md) |
| Frontend plan | [`../../satamu-invitation/docs/payment-checkout-plan.md`](../../satamu-invitation/docs/payment-checkout-plan.md) |

## Objective

Activate Satamu as the second production application on the existing order and Duitku payment core without duplicating gateway logic. The platform remains authoritative for package ownership, prices, order state, payment evidence, and reconciliation.

## Existing capability

- Application code `SATAMU` and invoice prefix `SAT`.
- Satamu order details: `theme_code`, `event_type`, `event_date`, and `notes`.
- Package codes `SAT-SEDERHANA`, `SAT-SPESIAL`, and `SAT-ISTIMEWA`.
- Satamu return URL and CORS origin configuration.
- Shared checkout, callback, payment status, back-office filtering, and reconciliation.

Satamu packages are intentionally inactive in production because the current frontend labels their content and prices as dummy. Activation must not happen until product approval.

## Scope

### Catalogue

- Approve package names, prices, features, revision limits, and service periods.
- Keep package codes permanent.
- Replace the environment-dependent production deactivation with an explicit catalogue activation mechanism.
- Reject a package when its application does not match the order's `application_code`.

### Public API

The existing endpoints remain the contract:

- `GET /api/v1/packages?application_code=SATAMU`
- `POST /api/v1/orders`
- `POST /api/v1/orders/{reference}/checkout`
- `GET /api/v1/orders/{reference}/payment-status`

Satamu clients send no price or total. `CreateOrder` resolves the amount from the active package catalogue and stores a price snapshot in the order item.

### Duitku

- Use the existing merchant project unless finance requires separate settlement.
- Use one callback endpoint and the existing verified transaction-check flow.
- Resolve return URL by order application: `PAYMENT_RETURN_URL_SATAMU`.
- Preserve the rule that only `ApplyPaymentEvidence` can move a payment to `PAID`.
- Never trust the browser return or callback `resultCode` without gateway confirmation.

### Operations

- Show Satamu orders, details, payment attempts, and evidence in the shared back office.
- Keep payment and order status separate.
- Record manual fulfilment notes until invitation provisioning is automated.
- Use the existing expiry, reconciliation, incident, backup, and rollback processes.

## Delivery phases

### Phase 1 — Gateway baseline

1. Complete review and merge of the Duitku adapter and callback hardening.
2. Pass static analysis, unit, feature, integration, browser, and build gates.
3. Validate Webworks regression before enabling a second application.

### Phase 2 — Satamu contract

1. Finalise and activate Satamu catalogue data.
2. Add explicit feature tests for all three package codes and cross-application rejection.
3. Confirm API/OpenAPI examples for Satamu details.
4. Verify `SAT-YYYYMM-NNNNNN` invoice generation.

### Phase 3 — Integrated staging

1. Run Satamu against the fake gateway locally.
2. Deploy platform and Satamu staging configuration.
3. Complete one Duitku sandbox checkout, callback, transaction check, return, and status-page flow.
4. Confirm the transaction is labelled Satamu in the back office.

### Phase 4 — Production rollout

1. Back up the platform database.
2. Deploy platform with Satamu packages still inactive.
3. Deploy Satamu with online payment disabled.
4. Run production health and CORS checks.
5. Activate approved Satamu packages.
6. Build Satamu with `PUBLIC_PLATFORM_API_URL` enabled.
7. Observe first orders, callbacks, reconciliation groups, failed jobs, and logs.

## Required tests

- Satamu order accepts a Satamu package and stores authoritative price.
- Satamu order rejects Webworks package codes and inactive packages.
- Webworks order rejects Satamu package codes.
- Replayed order and checkout keys return the existing resources.
- Callback signature, source, invoice, amount, and transaction status are verified.
- Amount mismatch and forged success never produce `PAID`.
- Paid Satamu order transitions to `CONFIRMED` once.
- Return URL is Satamu's status page and does not change state.
- CORS allows the approved Satamu origins only.
- Back office filtering and transaction detail show Satamu-specific fields.

## Security and privacy gates

- Publish customer consent and privacy text before activation.
- Do not log raw callback bodies, signatures, credentials, or unnecessary personal data.
- Keep request logging on route patterns instead of public order identifiers.
- Confirm production debug mode is off, HTTPS is enforced, and callback proxy/IP behaviour matches the runbook.
- Confirm secrets exist only in the deployment environment.

## Rollback

1. Disable Satamu packages in the catalogue to prevent new orders.
2. Rebuild Satamu without `PUBLIC_PLATFORM_API_URL` to restore WhatsApp-only ordering.
3. Do not delete existing orders or payments; continue callback handling and reconciliation for attempts already issued.
4. Roll back application releases only through the deployment runbook.

## Exit criteria

- Business approves real package and theme data.
- All required tests pass.
- Duitku sandbox callback and paid status are observed end to end.
- Privacy and refund information is published.
- Operations can identify, reconcile, and support a Satamu transaction.
- Rollback has been rehearsed without deleting payment history.
