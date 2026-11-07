# ADR-0010: Consolidate operational payment screens into the transaction workspace

- Status: Proposed
- Date: 2026-11-07
- Decision owners: Prasetya Group
- Related: [`../transaction-workspace-consolidation-plan.md`](../transaction-workspace-consolidation-plan.md), [ADR-0002](0002-mvp-payment-monitoring-scope.md), [ADR-0009](0009-single-access-users-username-login.md)

## Context

The first-release navigation exposes Transactions, Payment Events, Reconciliation, and Audit Log as separate top-level items. The transaction detail already shows payment attempts, payment events, related webhooks, order history, notes, and transaction-scoped audit. For a small operations team, separate primary navigation adds cognitive load and sends an investigator across screens for one invoice.

Some records cannot be reduced to a transaction: unknown webhook invoices, reconciliation issues without a payment attempt, and login/user/settings audit. Simplification must preserve those records and the existing safety invariants.

## Decision

- **Transactions** becomes the single top-level operational workspace with `all`, `attention`, and `events` views.
- Payment Events and Reconciliation are removed from the sidebar only after feature parity is proven in the workspace.
- Transaction-scoped audit is shown on transaction detail; user audit is shown on user detail; login/settings/system audit is available under Settings activity.
- Unmatched events and issues remain searchable in workspace views even when no transaction link exists.
- Gateway status checks move close to the relevant payment attempt but continue to call the existing domain action and produce audit evidence.
- Data models, append-only evidence, reconciliation groups, payment/order state machines, and `PAID` authority do not change.
- Old GET routes redirect to the new views for at least one release cycle.
- Delivery is progressive: enrich detail, add workspace views, relocate non-transaction audit, then simplify the sidebar.

## Alternatives considered

- **Keep every screen as a top-level menu.** Rejected as unnecessarily complex for the current team and action volume.
- **Delete the global event, reconciliation, and audit capabilities.** Rejected because unmatched evidence and non-transaction audit are operationally and security relevant.
- **Put every record in transaction detail.** Rejected because some evidence has no valid transaction relationship.
- **Build one universal SQL/event table.** Rejected because it couples unrelated domain records and makes security, pagination, and testing harder.
- **Use nested sidebar menus.** Rejected for the initial consolidation because secondary tabs inside one workspace better express a single operational context.

## Consequences

- The sidebar becomes shorter and daily investigation centres on invoice/order context.
- The transaction page contract becomes broader and requires explicit per-view payloads.
- System/user audit remains available but is no longer a primary navigation item.
- Compatibility routes and documentation must be maintained during transition.
- Query performance and tab state require dedicated tests and monitoring.
- Incident runbooks must link to the new destinations.

## Validation

- Feature parity tests for all payment event filters and all nine reconciliation groups.
- Transaction detail tests for scoped evidence, active issues, gateway check, and audit.
- Tests for unmatched evidence and non-transaction audit discovery.
- Compatibility redirect tests preserving relevant filters.
- Browser tests for the complete investigation workflow, keyboard navigation, mobile layout, and rollback-safe routing.
