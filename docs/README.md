# Prasetya Platform Documentation

This directory is the engineering source of truth for Prasetya Platform. Product, design, engineering, QA, security, and operations decisions should be discoverable from this index.

## Document map

| Document | Purpose | Status |
|---|---|---|
| [`design.md`](design.md) | Design system, component contracts, accessibility, and frontend quality baseline | Approved for implementation |
| [`architecture.md`](architecture.md) | System context, module boundaries, data ownership, and engineering constraints | Proposed |
| [`implementation-plan.md`](implementation-plan.md) | Delivery milestones, dependencies, exit criteria, quality gates, and risks | Draft for approval |
| [`satamu-payment-activation-plan.md`](satamu-payment-activation-plan.md) | Satamu catalogue, shared Duitku integration, tests, rollout, and rollback | Draft for approval |
| [`back-office-user-management-plan.md`](back-office-user-management-plan.md) | Pengguna module, one access level, and username sign-in | Implemented, awaiting acceptance |
| [`back-office-user-management-implementation-prompt.md`](back-office-user-management-implementation-prompt.md) | Coding-agent prompt for the user management plan (Indonesian) | Reference |
| [`transaction-workspace-consolidation-plan.md`](transaction-workspace-consolidation-plan.md) | Konsolidasi Transaksi, Event Pembayaran, Rekonsiliasi, dan Audit tanpa kehilangan capability | Draft untuk persetujuan |
| [`transaction-workspace-consolidation-implementation-prompt.md`](transaction-workspace-consolidation-implementation-prompt.md) | Prompt lengkap implementasi bertahap, test, rollout, dan rollback workspace Transaksi | Reference |
| [`content-studio-ai-planning-plan.md`](content-studio-ai-planning-plan.md) | Content Studio, KelontongAI copy generation, Hermes trend research/weekly planning, dan posting Instagram manual | Draft untuk persetujuan |
| [`content-studio-simplification-plan.md`](content-studio-simplification-plan.md) | Target penyederhanaan Kalender, form, status tanpa approval formal, campaign, migrasi, dan compatibility | Draft untuk persetujuan |
| [`content-studio-simplification-implementation-prompt.md`](content-studio-simplification-implementation-prompt.md) | Prompt coding-agent bertahap untuk lifecycle creator-owned, Calendar workspace, migrasi status, compatibility, dan weekly AI planning | Reference |
| [`content-studio-ai-implementation-prompt.md`](content-studio-ai-implementation-prompt.md) | Prompt coding-agent bertahap untuk Content Studio manual, OpenAI, research tools, Hermes/Docker, approval, dan posting manual | Reference |
| [`decisions/`](decisions/) | Architecture Decision Records (ADRs) for consequential technical choices | Active |
| [`assets/`](assets/) | Approved visual references used by design documentation | Reference |
| [`api/README.md`](api/README.md), [`api/openapi.yaml`](api/openapi.yaml) | Public REST API conventions and contract | Active |
| [`api/duitku-contract.md`](api/duitku-contract.md) | Duitku contract as implemented, and what sandbox must confirm | Active, payment flow verified against live sandbox |
| [`data-dictionary.md`](data-dictionary.md) | Tables, state machines, and data rules | Active |
| [`testing/strategy.md`](testing/strategy.md) | What each test layer proves and how to run it | Active |
| [`security/threat-model.md`](security/threat-model.md) | Threats, controls, and gaps before production payments | Proposed |
| [`runbooks/deployment.md`](runbooks/deployment.md) | Deploy, backup, and restore | Draft, not yet executed on target hosting |
| [`runbooks/payment-reconciliation.md`](runbooks/payment-reconciliation.md) | Working each reconciliation group | Active |
| [`runbooks/incident-response.md`](runbooks/incident-response.md) | What to do when payments misbehave | Active |
| [`runbooks/content-studio-ai.md`](runbooks/content-studio-ai.md) | Enable, limit, evaluate, and switch the AI copy provider | Current |
| [`runbooks/content-studio-hermes.md`](runbooks/content-studio-hermes.md) | Hermes runtime rules for Content Studio, local and VPS | Draft, nothing installed |

## Documentation standards

- Markdown is the canonical format and is versioned with the codebase.
- Each governing document states its owner, status, update date, and related documents.
- Requirements use testable language such as **must**, **required**, and explicit exit criteria.
- Long-lived technical decisions are recorded as ADRs; chat messages are not decision records.
- API contracts, runbooks, data dictionaries, and threat models are added before the related production capability ships.
- Secrets, credentials, personal data, and real payment payloads must never appear in documentation fixtures.

## Status vocabulary

| Status | Meaning |
|---|---|
| Draft | Under active preparation; not yet an implementation commitment |
| Proposed | Ready for review and decision |
| Approved for implementation | Accepted baseline for delivery |
| Active | In force and maintained |
| Superseded | Replaced by a newer document or decision |
| Deprecated | Still present temporarily but must not be used for new work |

## Review workflow

1. Author updates the relevant document and links affected ADRs or contracts.
2. Product reviews scope and user-visible behaviour.
3. Engineering reviews architecture, security, data, and operational impact.
4. QA confirms acceptance criteria and testability.
5. The owner updates the status and `lastUpdated` field after approval.
