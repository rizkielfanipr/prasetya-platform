# Prasetya Platform Delivery Plan

| Field | Value |
|---|---|
| Document status | Draft for approval |
| Delivery model | Incremental, milestone-based |
| Owner | Prasetya Group |
| Last updated | 2026-11-01 |
| Target release | To be scheduled after Duitku sandbox access is confirmed |
| Related documents | [`design.md`](design.md), [`architecture.md`](architecture.md) |

## Executive summary

Prasetya Platform will be delivered as a modular monolith: one Laravel repository containing REST APIs and a Vue/Inertia back office. MySQL is the system of record and Duitku provides checkout and payment notification capabilities. Delivery starts with product decisions and engineering foundations, validates the reusable UI using fixtures, then introduces business data and payment risk incrementally.

## Delivery status

Updated 2026-11-01. A milestone is marked done only when its exit criteria have evidence. Commands and results are in [`testing/strategy.md`](testing/strategy.md).

| ID | Status | Outstanding |
|---|---|---|
| M0 | Partly done | Satamu catalogue is dummy content on the site and inactive in production. One or two Duitku merchant accounts undecided. Refund and renewal rules not finalised |
| M1 | Done locally | The repository now has a GitHub remote; a passing CI run is not yet confirmed |
| M2 | Done | Delivered together with M8, see below |
| M3 | Done | |
| M4 | Done | Migrations and seeders verified on fresh MySQL 8.0 |
| M5 | Done for the API | Consumer sites not connected, see M7 and M9 |
| M6 | Done in sandbox | A Webworks order was paid by virtual account on 2026-11-01: the callback passed verification and the status check, and the payment became `PAID`. Duplicate and forged callbacks are proven by tests, not live. A QR payment on the hosted page is still open: see [`api/duitku-contract.md`](api/duitku-contract.md) |
| M7 | In progress | Choice dialog and status page built on `prasetya-webworks` branch `feat/platform-checkout`, verified locally with the simulated pay page. A sandbox order was paid end to end on 2026-11-01. Needs deployment; catalogue prices on the site are not yet read from the API at build time |
| M8 | Done on fake-gateway data | Export deferred. "Webworks operations no longer require database access" depends on M7 |
| M9 | Not started | Needs the real Satamu catalogue |
| M10 | Scheduler only | Expiry and pruning are scheduled. No customer or internal notifications yet |
| M11 | Partly done | Tests pass and docs exist. Backup restoration is not proven, and the threat model needs review |
| M12 | Not started | Requires M6, M7, M11 |

M2 ran as one piece with M8 rather than as a fixture-only stage: pages were built against typed props, exercised with fixtures in component tests, and connected to real queries directly, so no fixture data ships in the application.

## Goals

Build one Laravel platform that provides:

- REST APIs for `prasetya-webworks` and `satamu-invitation`.
- A custom Vue/Inertia back office in the same repository.
- MySQL persistence for multi-brand orders and payments.
- Duitku Pop creation, webhook verification, and payment monitoring.

The sequence deliberately establishes the approved UI and operational model before connecting real payment flows.

## Scope

### In scope

- Shared multi-brand order and payment platform.
- REST APIs consumed by Prasetya Webworks and Satamu Invitation.
- Admin authentication, authorisation, audit history, and operational back office.
- Duitku checkout creation, signed webhook verification, and reconciliation data.
- Responsive, accessible, reusable Vue/Inertia interface.
- Deployment to the existing cPanel-compatible environment.

### Out of scope for the first release

- Customer account portal.
- Automatic domain registration.
- Split payment or instalment management outside Duitku Pop capabilities.
- Automated WordPress invitation provisioning.
- Advanced accounting or tax reporting.
- Native mobile application.

## Assumptions and constraints

- MySQL remains available in the target hosting environment.
- Duitku sandbox and production credentials are provided separately and never committed.
- Both Astro sites communicate with versioned Laravel APIs.
- Laravel is authoritative for price, package availability, payment state, and authorisation.
- Production payment state changes only after verified backend notification.
- Dates are stored in UTC and presented in the configured business timezone.

## Delivery governance

- A milestone starts only when its dependencies and Definition of Ready are satisfied.
- Each milestone must meet its exit criteria before dependent work is merged.
- Architecture-impacting changes require an ADR in `docs/decisions/`.
- API contract changes are versioned and reviewed by consumers from both Astro projects.
- Security or payment defects block release regardless of schedule.

## Milestone overview

| ID | Milestone | Depends on | Primary outcome |
|---|---|---|---|
| M0 | Product and design baseline | — | Approved scope, states, design, and contracts |
| M1 | Project and codebase foundation | M0 | Reproducible project, CI, and module boundaries |
| M2 | Reusable static back-office UI | M1 | Responsive interface validated with fixtures |
| M3 | Authentication foundation | M1, M2 | Protected admin application |
| M4 | Multi-brand domain model | M1, M3 | Valid persistent business model |
| M5 | Order API | M4 | Safe unpaid-order lifecycle |
| M6 | Duitku sandbox core | M5 | Verified, idempotent payment lifecycle |
| M7 | Webworks pilot | M6 | First end-to-end consumer integration |
| M8 | Operational back office | M7 | Real monitoring and reconciliation workflow |
| M9 | Satamu integration | M6, M8 | Second brand on shared payment core |
| M10 | Queues and notifications | M7 | Reliable asynchronous communication |
| M11 | Security and operational readiness | M0–M10 | Release evidence and recovery readiness |
| M12 | Production rollout | M11 | Controlled production launch |

## M0 — Product and design baseline

### Work

- Approve login and dashboard visual references.
- Establish `docs/design.md` as the UI source of truth.
- Finalise package codes, prices, inclusions, renewal rules, revisions, refund rules, and domain handling for both brands.
- Define payment and fulfilment state machines.
- Decide whether both brands use one Duitku merchant account.
- Define API error envelope, invoice format, and idempotency policy.

### Exit criteria

- No unresolved price or package ambiguity is required for the first checkout.
- Payment and fulfilment statuses are documented separately.
- Design and implementation plan are approved.

## M1 — Project and codebase foundation

### Work

- Scaffold the Laravel Vue/Inertia starter with TypeScript, Tailwind, and shadcn-vue.
- Establish the modular backend and feature-based frontend folder structure.
- Enable TypeScript strict mode, ESLint, Prettier, Vue type checking, and production build checks.
- Configure unit, feature, component, and browser test foundations.
- Configure local environment templates without committing secrets.
- Add CI for formatting, linting, type checking, tests, and production builds.
- Document naming, module boundaries, API contracts, and architectural decisions.

### Exit criteria

- A clean checkout can be installed, built, tested, and run from documented commands.
- CI passes on the empty application foundation.
- Backend modules and frontend feature boundaries are documented before feature work begins.

## M2 — Static reusable back-office UI

### Work

- Implement semantic tokens from `docs/design.md`.
- Build reusable shadcn-vue primitives, layout components, and shared application components before composing pages.
- Build static responsive screens using fixtures:
  - Login
  - Dashboard shell
  - Sidebar and header
  - Transaction table
  - Orders list and order detail
  - Payment detail
- Cover loading, empty, error, and permission-denied states.
- Add typed props, stable variants, and component tests for shared components.
- Lazy-load charts and non-critical feature modules; verify the production bundle output.
- Verify keyboard navigation, focus management, contrast, and reduced-motion behaviour.
- Verify desktop, tablet, and mobile layouts.

### Exit criteria

- UI matches the approved references and design tokens.
- Navigation and responsive behaviour work with fixture data.
- Pages reuse documented components instead of copying page-specific markup.
- Type checking, component tests, accessibility checks, and production build pass.
- No Duitku or production database dependency exists yet.

## M3 — Laravel foundation and authentication

### Work

- Configure environment separation for local, staging, and production.
- Connect MySQL and create the initial admin user model.
- Implement session-based admin authentication through Laravel/Inertia.
- Add password reset, rate limiting, CSRF protection, policies, and audit-ready logging.
- Add `/api/v1/health` and application diagnostics.
- Establish automated tests and CI checks.

### Exit criteria

- Admin login is real and protected.
- Unauthenticated users cannot access back-office routes.
- Health check and CI pass.

## M4 — Multi-brand domain model

### Work

- Create migrations and models for:
  - applications
  - customers
  - packages
  - orders
  - order_items
  - payments
  - payment_events
  - webhook_events
  - order_status_histories
  - admin_notes
  - webworks_order_details
  - invitation_order_details
- Seed `WEBWORKS` and `SATAMU` applications.
- Seed package catalogues with stable package codes.
- Implement invoice prefixes `PW` and `SAT`.
- Add foreign keys, unique references, transactions, and idempotency constraints.

### Exit criteria

- Seeded packages and applications can create valid test orders.
- Duplicate invoice and payment references are rejected by the database.
- Migration can run cleanly on a fresh MySQL database.

## M5 — Order API without payment

### Work

- Implement versioned endpoints for package listing, order creation, order detail, and public order status.
- Make Laravel authoritative for package price and availability.
- Validate brand-specific order details.
- Add CORS allowlists for both Astro origins.
- Add public endpoint rate limits and idempotency keys.
- Connect fixture-based admin pages to real order data.

### Exit criteria

- Both brands can create unpaid orders through API tests.
- Frontend-supplied prices are ignored.
- Admin can find and inspect orders in the Vue back office.

## M6 — Duitku sandbox payment core

### Work

- Build a dedicated Duitku service for signature generation and checkout creation.
- Store each payment attempt separately from its order.
- Return a Duitku Pop URL only for a valid payable order.
- Implement the public webhook endpoint.
- Verify webhook signature, invoice, amount, and payment transition.
- Make webhook processing idempotent and audit every received event.
- Implement pending, success, failed, and expired status handling.
- Add reconciliation tests for duplicate and out-of-order notifications.

### Exit criteria

- Sandbox checkout completes successfully.
- Only a verified webhook can mark a payment as paid.
- Duplicate webhook delivery produces no duplicate payment or side effect.

## M7 — Prasetya Webworks checkout pilot

### Work

- Finalise Webworks package data and modal steps.
- Collect package, template, customer, business, and domain preferences.
- Create the order through Laravel, then request checkout.
- Open Duitku through redirect or approved popup flow.
- Add pending, success, and failure pages that query backend status.
- Prevent refresh and repeat click from creating duplicate orders.
- Add the Webworks order and project workflow to back office.

### Exit criteria

- A Webworks customer can complete an end-to-end sandbox order.
- The order and verified payment appear in back office.
- The displayed amount always matches Laravel's package catalogue.

## M8 — Payment and operations back office

### Work

- Replace remaining dashboard fixtures with real aggregate queries.
- Add filters for brand, payment status, fulfilment status, and date range.
- Add order detail, payment attempts, webhook history, notes, and status timeline.
- Separate finance actions from project fulfilment actions.
- Add roles and policies for super admin, finance, and operations if required.
- Add export and reconciliation views only after core workflows are stable.

### Exit criteria

- Admin can reconcile Duitku payment to invoice and customer.
- Every manual status change is authorised and recorded.
- Webworks operations no longer require direct database access.

## M9 — Satamu Invitation integration

### Work

- Finalise Satamu package catalogue and event-specific order fields.
- Connect Satamu checkout to the shared order and payment core.
- Use `SAT` invoice prefixes and Satamu-specific details.
- Add invitation fulfilment views to back office.
- Confirm that Webworks and Satamu filters, permissions, and reporting remain isolated.

### Exit criteria

- Satamu completes the same sandbox payment lifecycle.
- Shared payment code is reused; only product-specific order data differs.
- Both brands are visible and filterable in one dashboard.

## M10 — Notifications, queues, and scheduled work

### Work

- Queue customer and internal payment notifications.
- Add retry and failed-job handling.
- Add reminders for pending payments where permitted.
- Add renewal reminders for domain, hosting, or invitation active period.
- Configure cPanel cron for scheduler and queue processing.
- Keep notification failures separate from payment state changes.

### Exit criteria

- Payment processing succeeds even when notification delivery fails.
- Failed jobs are visible and retryable.
- Scheduled tasks run reliably on the target hosting environment.

## M11 — Security, QA, and operational readiness

### Work

- Test tampered prices, duplicate submit, invalid package, and inactive package.
- Test successful, failed, expired, repeated, and out-of-order payments.
- Test invalid webhook signature, mismatched amount, replay, and unknown invoice.
- Test admin authorisation, CSRF, session expiry, rate limits, and CORS.
- Add secret handling, log redaction, MySQL backup, and restore procedure.
- Run accessibility and responsive checks against the design system.
- Document incident response and Duitku reconciliation procedure.

### Exit criteria

- Critical payment and authorisation tests pass.
- Backup restoration is proven, not merely configured.
- No secret or sensitive payload is exposed in frontend bundles or logs.

## M12 — Staging and production rollout

### Work

- Deploy Laravel/Inertia to the platform subdomain.
- Configure MySQL, SSL, storage, cron, queue, and production secrets.
- Register the public HTTPS Duitku notification URL.
- Run a small controlled production transaction.
- Reconcile the payment in Duitku and Prasetya Platform.
- Roll out Webworks first, monitor, then enable Satamu.

### Exit criteria

- Production webhook and checkout are verified end to end.
- Monitoring and rollback procedures are available.
- Both Astro sites use the production API only after the pilot is stable.

## Recommended delivery order

```text
Design baseline
→ Project and codebase foundation
→ Reusable static admin UI
→ Authentication
→ MySQL domain model
→ Order API
→ Duitku sandbox core
→ Webworks pilot
→ Operational back office
→ Satamu integration
→ Notifications
→ Security and QA
→ Production rollout
```

## Definition of Ready

A work item is ready when:

- acceptance criteria and affected user or system are clear;
- design states or API contract are available where applicable;
- dependencies, security impact, and data migration impact are identified;
- test approach is known;
- unresolved decisions are linked rather than hidden in implementation notes.

## Definition of Done

A work item is done when:

- implementation follows the documented module and component boundaries;
- formatting, linting, strict type checking, tests, and production build pass;
- validation, authorisation, loading, empty, error, and audit behaviour are covered as applicable;
- accessibility and responsive behaviour are verified for UI changes;
- logs contain useful context without secrets or sensitive payloads;
- API or operational documentation is updated;
- deployment and rollback implications are understood.

## Quality gates

| Gate | Required evidence |
|---|---|
| Code quality | Formatter, linter, strict type check, code review |
| Automated testing | Unit, feature/integration, component, and critical browser tests |
| Security | Authorisation, input validation, secret handling, dependency review |
| Payment integrity | Signature, amount, invoice, replay, duplicate, and state-transition tests |
| UX quality | Approved states, responsive checks, keyboard flow, WCAG AA contrast |
| Operations | Structured logging, health check, backup/restore, runbook, rollback |

## Risk register

| Risk | Impact | Mitigation |
|---|---|---|
| Duitku credentials or merchant setup delayed | Payment milestones blocked | Complete M0–M5 with adapters and fixtures; confirm access before M6 |
| Duplicate or out-of-order webhooks | Incorrect payment state | Database constraints, idempotent event storage, transition rules, replay tests |
| Shared platform leaks brand data | Operational/privacy issue | Explicit application scope, policies, filtered queries, cross-brand tests |
| cPanel queue limitations | Delayed notifications | Validate cron/worker strategy early; keep notification failure independent of payment |
| Frontend components diverge | Slow maintenance and inconsistent UX | Enforce design tokens, documented variants, component tests, review checklist |
| Package prices drift across sites | Incorrect checkout amount | Laravel catalogue is authoritative; Astro never submits a trusted price |

## Change control

- Minor copy and documentation corrections may update the relevant document directly.
- Component API, domain boundary, payment flow, persistence, or deployment changes require review and an ADR.
- Every document update records its date and keeps links to superseding decisions.
- Deferred scope is returned to the backlog; it is not silently added to an active milestone.

