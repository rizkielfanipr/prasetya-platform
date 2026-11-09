# Architecture Decision Records

ADRs capture consequential technical decisions, their context, alternatives, and trade-offs. They are immutable after acceptance except for spelling or link corrections; a new ADR supersedes an old decision.

## Naming

Use `NNNN-short-title.md`, for example `0002-api-error-envelope.md`.

## Statuses

- Proposed
- Accepted
- Rejected
- Superseded by ADR-NNNN
- Deprecated

## Template

```markdown
# ADR-NNNN: Decision title

- Status: Proposed
- Date: YYYY-MM-DD
- Decision owners: Name or team
- Related: Links

## Context

What problem or constraint requires a decision?

## Decision

What is being chosen?

## Alternatives considered

What credible options were evaluated?

## Consequences

What becomes easier, harder, required, or intentionally deferred?

## Validation

How will the decision be verified in implementation and operation?
```

## Index

| ADR | Decision | Status |
|---|---|---|
| [0001](0001-modular-monolith.md) | Use a Laravel modular monolith with Vue/Inertia | Proposed |
| [0002](0002-mvp-payment-monitoring-scope.md) | First release is a payment monitoring back office | Proposed |
| [0003](0003-accessible-colour-tokens.md) | Derived colour tokens for WCAG AA and chart separation | Proposed |
| [0004](0004-doku-checkout-contract.md) | How the platform applied the DOKU Checkout contract | Superseded by ADR-0007 |
| [0005](0005-laravel-skeleton-and-tooling.md) | Plain Laravel skeleton with explicit tooling | Proposed |
| [0006](0006-money-identifiers-idempotency.md) | Integer rupiah, public ULIDs, and reference-based idempotency | Proposed |
| [0007](0007-duitku-gateway.md) | Use Duitku Pop as the only payment gateway | Proposed |
| [0008](0008-satamu-payment-activation.md) | Activate Satamu on the shared order and payment core | Proposed |
| [0009](0009-single-access-users-username-login.md) | One access level, back-office user management, and username login | Proposed |
| [0010](0010-consolidated-transaction-workspace.md) | Consolidate operational payment screens into the transaction workspace | Proposed |
| [0011](0011-content-studio-authority-boundary.md) | Content Studio, with AI providers and Hermes as untrusted draft producers | Proposed |
| [0012](0012-simplified-content-studio-workflow.md) | Simplify Content Studio to a creator-owned workflow without formal approval | Proposed |
