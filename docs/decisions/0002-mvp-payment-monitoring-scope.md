# ADR-0002: First release is a payment monitoring back office

- Status: Proposed
- Date: 2026-11-01
- Decision owners: Prasetya Group
- Related: [`../design.md`](../design.md), [`../architecture.md`](../architecture.md), [`../implementation-plan.md`](../implementation-plan.md)

## Context

`design.md` and `architecture.md` describe a wider product: navigation for Pesanan, Pembayaran, Pelanggan, and Paket & Layanan, a `Fulfilment` domain module, and feature folders named after those areas. The implementation brief narrows the first release to monitoring orders and payments and excludes customer and package CRUD, fulfilment, and manual payment overrides. It was also delivered as two overlapping prompts that disagree on some names, so those choices need one written answer.

## Decision

Navigation for this release is Dashboard, Transaksi, Event Pembayaran, Rekonsiliasi, Audit Log, Pengaturan.

| Topic | Decision | Why |
|---|---|---|
| Domain modules | `Audit` added; `Fulfilment` deferred | Audit is in scope, fulfilment is not. Its absence removes `FulfilmentStatusBadge` from this release |
| Frontend features | `auth`, `dashboard`, `transactions`, `payments`, `reconciliation`, `audit`, `settings` | Matches the screens that exist |
| Order paid state | `CONFIRMED`, not `PAID` | "Paid" belongs to the payment. A second `PAID` would blur the two state machines the architecture keeps separate |
| Roles | Super Admin, Finance, Operations | Read Only was conditional ("if needed") and nothing needs it yet |
| Public order identifier | ULID, exposed as `reference` | Not guessable by counting; route parameter is the ULID |
| Notification route | `POST /api/v1/webhooks/doku` | DOKU has no fixed path: the merchant sets the Notification URL |
| Reconciliation | Observational. The only action is a status check against the gateway | One brief allowed audited corrective actions, the other excluded overrides. A check satisfies both because it asserts nothing itself |
| Reconciliation groups | Union of both briefs, nine groups | Each brief named groups the other did not |

## Alternatives considered

- **Keep the design's navigation with empty sections.** Rejected: it ships screens that do nothing.
- **Build a generic corrective-action framework now.** Rejected: no approved corrective action exists to run through it.

## Consequences

- `design.md` navigation is amended for this release; Pelanggan and Paket & Layanan return with their CRUD scope.
- Customers and packages exist as backend data and are changed by seeders until then.
- Webworks operations still need a separate place to track project work.

## Validation

Feature tests cover the role matrix and that no route sets a payment status directly. Browser tests cover the navigation per role.
