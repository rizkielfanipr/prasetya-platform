# ADR-0008: Activate Satamu on the shared order and payment core

- Status: Proposed
- Date: 2026-11-06
- Decision owners: Prasetya Group
- Related: [`../satamu-payment-activation-plan.md`](../satamu-payment-activation-plan.md), [ADR-0007](0007-duitku-gateway.md), [`../../../satamu-invitation/docs/decisions/0004-online-payment-through-platform.md`](../../../satamu-invitation/docs/decisions/0004-online-payment-through-platform.md)

## Context

Prasetya Platform already models Webworks and Satamu as separate applications and shares order, payment, webhook, reconciliation, and back-office capabilities. Satamu needs direct payment, but a second gateway integration would duplicate security-sensitive state transitions and evidence handling. Its current packages and themes are still labelled dummy, and package seeding therefore disables Satamu in production.

## Decision

- Satamu uses the existing platform order and Duitku payment core with `application_code=SATAMU` and `SAT` invoice prefixes.
- The platform catalogue is the only price authority. Browser-supplied prices are never accepted.
- Satamu package activation is explicit catalogue state, not an automatic consequence of the application environment.
- Package ownership is enforced: a Satamu order can only buy an active Satamu package.
- Satamu and Webworks share the callback endpoint, payment state machine, reconciliation, and merchant project unless finance later requires separate settlement.
- Return URLs are selected by application. A browser return never changes payment state.
- Existing payment invariants remain unchanged: only `ApplyPaymentEvidence` produces `PAID`, and a Duitku success callback requires a server-to-server status check.
- Invitation provisioning remains manual after payment for the first release.

## Alternatives considered

- **A separate Satamu payment service.** Rejected because it duplicates idempotency, callback verification, state transitions, reconciliation, and operations.
- **Direct Duitku integration in Astro.** Rejected because secrets and trustworthy payment evidence require a backend.
- **Activate packages automatically in production.** Rejected because dummy or unapproved catalogue data must fail closed.
- **Separate Duitku merchant immediately.** Deferred until a legal, accounting, or settlement requirement justifies the operational cost.

## Consequences

- Satamu can launch with the same payment safety and back-office controls as Webworks.
- A platform outage affects direct payment for both brands, so each frontend keeps a WhatsApp fallback.
- Product approval and privacy documentation become production gates.
- Price changes must be made in the platform catalogue and reflected in static marketing content.
- Existing payment attempts must continue to receive callbacks even when Satamu ordering is disabled.

## Validation

- Feature tests for Satamu packages, application ownership, idempotency, invoice prefix, and return URL.
- Duitku integration and callback tests using Satamu orders.
- End-to-end sandbox payment from Satamu through the shared back office.
- Operational verification of catalogue disable, frontend fallback, reconciliation, and rollback.
