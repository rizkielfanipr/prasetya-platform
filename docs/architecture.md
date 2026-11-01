# Prasetya Platform Architecture

| Field | Value |
|---|---|
| Status | Proposed |
| Owner | Prasetya Group |
| Last updated | 2026-11-01 |
| Architecture style | Modular monolith |
| Related documents | [`design.md`](design.md), [`implementation-plan.md`](implementation-plan.md), [`decisions/0001-modular-monolith.md`](decisions/0001-modular-monolith.md) |

## Purpose

This document defines the intended system boundaries and engineering rules for Prasetya Platform. It describes stable architecture, not implementation task detail.

## System context

```text
Prasetya Webworks (Astro) ─┐
                            ├── HTTPS /api/v1 ── Prasetya Platform
Satamu Invitation (Astro) ─┘                    ├── Laravel REST API
                                                ├── Vue/Inertia back office
Admin browser ───────── HTTPS/session ──────────┤
                                                ├── MySQL
                                                ├── Queue / scheduler
DOKU ───────────── signed webhook / checkout ──┘
```

## Architectural drivers

- One small team must operate two brands without duplicating payment logic.
- Payment integrity, auditability, and recovery are more important than distributed scalability.
- The application must deploy in the available cPanel environment.
- Frontend code must remain reusable and accessible as operational screens grow.
- A future extraction into services must remain possible without designing microservices prematurely.

## Architecture principles

1. **Modular monolith first:** one deployable application with explicit domain boundaries.
2. **Backend authority:** Laravel owns price, authorisation, payment state, and invariants.
3. **Thin delivery layers:** controllers and Inertia pages coordinate; domain actions implement rules.
4. **External systems behind adapters:** DOKU-specific code stays outside the core payment domain.
5. **Idempotency at every payment boundary:** duplicate requests and callbacks must be safe.
6. **Observability without leakage:** structured logs identify events without exposing secrets or full sensitive payloads.
7. **Accessible reusable UI:** pages compose typed, tested component contracts.

## Backend module boundaries

```text
app/
├── Domain/
│   ├── Auth/
│   ├── Brands/
│   ├── Customers/
│   ├── Packages/
│   ├── Orders/
│   ├── Payments/
│   └── Audit/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Controllers/BackOffice/
│   ├── Requests/
│   └── Resources/
├── Integrations/Doku/
├── Jobs/
├── Notifications/
├── Policies/
└── Support/
```

### Boundary rules

- Controllers contain no business transaction logic.
- Form Requests validate transport input; policies authorise actions.
- API Resources define external response contracts.
- Domain actions/services own state transitions and database transactions.
- Eloquent models must not call DOKU or other network services.
- Cross-module changes use explicit actions or contracts, not hidden model side effects.
- Payment and order status are separate state machines; fulfilment is a future third ([ADR-0002](decisions/0002-mvp-payment-monitoring-scope.md)).
- `tests/Architecture/BoundariesTest.php` enforces the prohibited dependencies.
- `ApplyPaymentEvidence` is the only path to `PAID` ([ADR-0004](decisions/0004-doku-checkout-contract.md)).

## Frontend module boundaries

```text
resources/js/
├── components/
│   ├── ui/
│   ├── layout/
│   └── shared/
├── features/
│   ├── auth/
│   ├── dashboard/
│   ├── transactions/
│   ├── payments/
│   ├── reconciliation/
│   ├── audit/
│   └── settings/
├── pages/
├── composables/
├── lib/
└── types/
```

### Boundary rules

- `components/ui` is domain-neutral and based on shadcn-vue source components.
- `pages` composes features and handles page-level orchestration; it does not duplicate component systems.
- Feature-specific components remain inside their feature until a stable cross-feature use case exists.
- API and Inertia props use explicit TypeScript contracts; `any` is prohibited.
- Formatters, routes, status labels, and brand mappings have one source of truth.
- Expensive charts and feature panels are loaded only when needed.

## Data ownership

MySQL is the system of record for applications, customers, packages, orders, payment attempts, payment events, webhook events, fulfilment state, and audit history. DOKU remains authoritative for gateway-side transaction evidence, while the platform stores verified events and its own resulting state.

Core integrity controls:

- foreign keys for ownership and lifecycle relationships;
- unique invoice and external payment references;
- idempotency keys for public mutations;
- immutable webhook/event records where practical;
- database transactions around state transitions;
- UTC storage and explicit presentation timezone.

## Interface conventions

- Public APIs are versioned under `/api/v1`.
- Laravel recalculates price from package code; consumer-provided prices are never trusted.
- Errors use a consistent machine-readable code, human-readable message, field errors where relevant, and request ID.
- Back-office requests use session authentication and CSRF protection.
- Public APIs use explicit CORS allowlists, rate limits, and idempotency for mutations.
- Webhooks verify signature, timestamp/replay conditions, invoice, amount, and allowed transition before changing state.

## Security boundaries

- DOKU secret, database credentials, and application keys exist only in server environment configuration.
- Logs redact secrets, authorisation headers, personal data, and raw signed payloads.
- Permissions are enforced server-side through policies; hidden UI is not an authorisation control.
- Administrative state changes produce an actor, timestamp, previous state, new state, and reason where applicable.
- Dependency and threat-model reviews are required before production payment activation.

## Reliability and operations

- Health endpoints distinguish application availability from dependency health where practical.
- Queue retries are bounded and failed jobs remain inspectable.
- Notification failure never reverses or fabricates payment state.
- Webhook processing is idempotent and safe for retries and out-of-order delivery.
- Database backup and restore are tested before production rollout.
- Structured logs include request ID, operation, application/brand context, result, and duration.

## Testing strategy

| Layer | Primary responsibility |
|---|---|
| Unit | Pure domain rules, formatters, signatures, state-transition policies |
| Feature | Routes, validation, policies, persistence, API envelopes |
| Integration | DOKU adapter contracts, webhook verification, database constraints |
| Component | Reusable Vue variants, states, keyboard interactions |
| Browser | Login, order search, payment inspection, critical operational paths |
| Contract | API compatibility with both Astro consumers |

## Deployment topology

The initial release uses one Laravel/Vue deployment and one MySQL database. Scheduler and queues use mechanisms supported by the target cPanel environment. Webworks and Satamu stay independently deployed Astro applications and consume the platform over HTTPS.

## Evolution rules

- Add a new domain module when ownership and invariants are distinct, not merely because a folder is large.
- Extract a service only after operational evidence shows an independent scaling, ownership, or deployment need.
- Replace DOKU through the integration adapter without rewriting order-domain rules.
- Record material changes in an ADR and update this document when the accepted architecture changes.
