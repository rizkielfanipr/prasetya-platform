# ADR-0001: Use a Laravel modular monolith with Vue/Inertia

- Status: Proposed
- Date: 2026-11-01
- Decision owners: Prasetya Group
- Related: [`../architecture.md`](../architecture.md)

## Context

Prasetya Webworks and Satamu Invitation need shared order, payment, customer, and operational capabilities. The team must maintain the platform on a cPanel-compatible environment while avoiding duplicated DOKU logic and the operational cost of multiple backend services.

## Decision

Build one Laravel modular monolith containing:

- versioned REST APIs for both Astro websites;
- a Vue 3 and Inertia back office in the same repository;
- MySQL persistence;
- explicit domain modules for orders, payments, customers, packages, brands, and fulfilment;
- DOKU behind a dedicated integration adapter.

Module boundaries are enforced through code organisation, contracts, tests, and review rather than separate network deployments.

## Alternatives considered

### Separate backend per brand

Rejected because it duplicates payment, customer, audit, and operational capabilities and makes consolidated monitoring harder.

### Independent API and SPA repositories

Deferred because separate deployment, authentication, contract synchronisation, and CI pipelines add complexity without a current ownership or scaling benefit.

### Microservices

Rejected for the initial release because service discovery, distributed transactions, observability, and operational overhead exceed the current product need and hosting model.

## Consequences

Positive:

- one deployment and shared payment implementation;
- simple session authentication for the back office;
- atomic database transactions across order and payment state;
- lower operational cost;
- clear path to reuse between both brands.

Trade-offs:

- module boundaries require discipline because the compiler does not enforce service isolation;
- one deployment can affect all modules;
- database and application scaling are initially coupled.

Required controls:

- no business logic in controllers;
- explicit domain actions and state-transition rules;
- DOKU isolated under an integration adapter;
- architecture tests or review checks for prohibited dependencies;
- future service extraction only with measured operational justification.

## Validation

- Both brands complete contract tests against the same versioned API.
- Payment and order modules remain testable without live DOKU access.
- A clean deployment serves both API and back-office routes.
- Code review confirms controllers and Vue pages remain thin orchestration layers.
