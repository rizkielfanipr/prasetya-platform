# ADR-0011: Content Studio, with AI providers and Hermes as untrusted draft producers

- Status: Proposed
- Date: 2026-11-07
- Decision owners: Prasetya Group
- Related: [`../content-studio-ai-planning-plan.md`](../content-studio-ai-planning-plan.md), [ADR-0001](0001-modular-monolith.md), [ADR-0009](0009-single-access-users-username-login.md), [ADR-0010](0010-consolidated-transaction-workspace.md)

## Context

The team wants to plan, write, review, and post single-image Instagram feed content every week, with AI help for research, strategy, copy, and images. AI output can be wrong, can invent business facts, and can be steered by hostile web content. Posting stays manual. The platform already holds orders and payments, whose invariants must not be touched, and it runs on cPanel shared hosting without Docker or a queue worker.

## Decision

1. **Content Studio is a module of Prasetya Platform** (`App\Domain\Content`), with Laravel and MySQL as the source of truth for brands, items, revisions, approvals, publications, and audit. It shares no tables, actions, or queues with orders and payments.
2. **Delivery is manual first.** Phase 1 has no AI: people create, revise, review, approve, and record posting. Every later AI feature is optional, and the manual workflow keeps working when any provider or Hermes is down or disabled.
3. **AI is an untrusted draft producer.** Copy, images, research, weekly plans, and briefs from any model or agent are stored as drafts and validated like user input. No AI path can approve, publish, reject, delete, change brand rules, campaigns, or business facts, or manage credentials.
4. **AI providers are configuration, per capability.** Copy generation, image generation, research search and extraction, and the agent each sit behind a contract in `App\Domain\Content`, with adapters in `App\Integrations\<Provider>`. Provider and model names come from configuration. OpenAI is the first copy and image provider. A provider without image generation (for example a text-only router such as Command Code) can still serve copy while another provider serves images. Switching provider requires the evaluation fixtures to pass first.
5. **Hermes is a separate, least-privilege service.** It runs in a container (Docker Desktop locally, Docker Engine with Compose on a VPS) and reaches Laravel only through authenticated internal tools with schemas, run ids, idempotency keys, expected versions, rate limits, and budgets enforced by Laravel. It never receives database credentials, Laravel's `.env`, SSH or deploy keys, the Docker socket, backups, or browser profiles. The cPanel host cannot run it; where it runs in staging and production is an open decision.
6. **Revisions are immutable once submitted.** An approval names one revision. Changing approved content creates a new revision, invalidates the old approval for publishing, and returns the item to review.
7. **Four-eyes approval.** The user who submitted a revision for review cannot approve it; another active user must. All other actions are open to every active user (ADR-0009).
8. **Publishing is a recorded human statement.** `PUBLISHED` comes only from a person marking an approved, current revision as posted, with the actual posting time (required) and the Instagram URL (optional). A planned date passing never publishes anything. There is no Instagram API and no n8n.
9. **Images are 4:5, 1080×1350.** Uploads must be JPEG, PNG, or WebP by their bytes, exactly 4:5, at least 1080×1350, and within size and pixel limits. Files are stored privately by checksum and served only to signed-in users.
10. **Content audit lives on the content item** (ADR-0010): entity `content_item` is shown on its detail page, not in system activity.

## Owner decisions

| Decision | Answer |
|---|---|
| First brand | Prasetya Webworks |
| Approval | Not by the submitter of the revision (four-eyes) |
| Image | Single image, 4:5, 1080×1350 |
| Instagram URL when marking published | Optional; the actual posting time is required |

Open until the phase that needs them: items per week and days (calendar does not enforce a count), fully generative versus brand assets for AI images, retention of drafts, research snapshots, generation logs and agent traces, OpenAI budget per run, week and brand, search and extraction provider, whether manual insights ship in the first release, where Hermes and the queue worker run in staging and production.

## Alternatives considered

- **Let the agent post directly through the Instagram API.** Rejected: publishing must be a human decision, and the API adds credentials and review requirements not needed for a weekly cadence.
- **Store content in Hermes or a separate content service.** Rejected: approval, versioning, and audit must live where the team already works and where they are tested.
- **Hard-wire OpenAI in the domain.** Rejected: provider prices, capabilities, and terms change; a contract per capability keeps the switch a configuration and evaluation step.
- **Approval by anyone, including the submitter.** Rejected by the owner in favour of four-eyes.

## Consequences

- At least two active users are needed to publish anything.
- Each AI phase adds an adapter, a budget, and tests without touching the manual workflow.
- AI phases need a queue worker and, for Hermes, a container host that the current cPanel server does not provide.
- Content history only grows: revisions, approvals, publications, and audit are never deleted by the workflow.

## Validation

- Feature tests for every state transition, four-eyes approval, revision immutability, stale-approval publishing refusal, upload validation by bytes, private download, and audit.
- Architecture tests that `App\Domain\Content` does not use HTTP clients and that content code does not touch payment or order models.
- For AI phases: faked providers, budget, and contract tests, plus evaluation fixtures before enabling or switching a provider.
