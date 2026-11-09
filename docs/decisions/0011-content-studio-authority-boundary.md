# ADR-0011: Content Studio, with AI providers and Hermes as untrusted draft producers

- Status: Proposed
- Date: 2026-11-07
- Decision owners: Prasetya Group
- Related: [`../content-studio-ai-planning-plan.md`](../content-studio-ai-planning-plan.md), [ADR-0012](0012-simplified-content-studio-workflow.md), [ADR-0001](0001-modular-monolith.md), [ADR-0009](0009-single-access-users-username-login.md), [ADR-0010](0010-consolidated-transaction-workspace.md)

> ADR-0012 proposes superseding decisions 6–8 and the four-eyes owner decision once the compatibility migration ships. Until then, this ADR describes the implemented runtime workflow.

## Context

The team wants to plan, write, review, and post single-image Instagram feed content every week, with AI help for research, strategy, copy, and images. AI output can be wrong, can invent business facts, and can be steered by hostile web content. Posting stays manual. The platform already holds orders and payments, whose invariants must not be touched, and it runs on cPanel shared hosting without Docker or a queue worker.

## Decision

1. **Content Studio is a module of Prasetya Platform** (`App\Domain\Content`), with Laravel and MySQL as the source of truth for brands, items, revisions, approvals, publications, and audit. It shares no tables, actions, or queues with orders and payments.
2. **Delivery is manual first.** Phase 1 has no AI: people create, revise, review, approve, and record posting. Every later AI feature is optional, and the manual workflow keeps working when any provider or Hermes is down or disabled.
3. **AI is an untrusted draft producer.** Copy, images, research, weekly plans, and briefs from any model or agent are stored as drafts and validated like user input. No AI path can approve, publish, reject, delete, change brand rules, campaigns, or business facts, or manage credentials.
4. **AI providers are configuration, per capability.** Copy generation, research search and extraction, and the agent each sit behind a contract in `App\Domain\Content`, with adapters in `App\Integrations` (`App\Integrations\ContentAi` for copy). Provider and model names come from configuration. The copy adapter speaks the OpenAI-compatible Chat Completions API; KelontongAI is the selected first copy provider. Images are uploaded manually because no usable Image API model is active. Personal subscriptions are not API access and are not routed through proxies. Switching provider requires the evaluation fixtures to pass first.
5. **Hermes is a separate, least-privilege service.** It runs in a container (Docker Desktop locally, Docker Engine with Compose on a VPS) and reaches Laravel only through authenticated internal tools with schemas, run ids, idempotency keys, expected versions, rate limits, and budgets enforced by Laravel. It never receives database credentials, Laravel's `.env`, SSH or deploy keys, the Docker socket, backups, or browser profiles. The cPanel host cannot run it; where it runs in staging and production is an open decision.
6. **Revisions are immutable once submitted.** An approval names one revision. Changing approved content creates a new revision, invalidates the old approval for publishing, and returns the item to review.
7. **Four-eyes approval.** The user who submitted a revision for review cannot approve it; another active user must. All other actions are open to every active user (ADR-0009).
8. **Publishing is a recorded human statement.** `PUBLISHED` comes only from a person marking an approved, current revision as posted, with the actual posting time (required) and the Instagram URL (optional). A planned date passing never publishes anything. There is no Instagram API and no n8n.
9. **Images are 4:5, 1080×1350.** Uploads must be JPEG, PNG, or WebP by their bytes, exactly 4:5, at least 1080×1350, and within size and pixel limits. Files are stored privately by checksum and served only to signed-in users.
10. **Content audit lives on the content item** (ADR-0010): entity `content_item` is shown on its detail page, not in system activity.
11. **Brand Knowledge is one free-text, versioned source document.** The operator sees one textarea and may describe the audience, voice, services, prices, processes, policies, FAQ, and other approved information in ordinary language. Saving creates an immutable version and deterministic chunks; it does not train or fine-tune a model. Copy generation retrieves the most relevant active chunks and sends only those as `brand_knowledge` in the untrusted DATA section of prompt `copy-v4`. The first retrieval implementation is keyword-based; the chunk contract may gain embeddings later without changing this UI or source-of-truth model.
12. **Campaign stays in planning.** Campaign management belongs to Calendar because it is time-bound. Legacy `?tab=campaigns` links redirect there. The earlier structured brand profiles, pillars, and approved facts remain available to validators and existing records during the transition, but they are no longer separate user-facing Brand tabs.
13. **Brand Knowledge assistance is a reviewable interview, not an autonomous write.** The editor may open a fixed, human-readable questionnaire covering identity, audience, offers and prices, differentiators, voice, visual direction, approved and forbidden claims, CTA, process, policies, and FAQ. Prompt `brand-knowledge-v2` converts only those answers plus the current document into an operational brand guide: it may derive editorial do/don't guidance but cannot invent business facts. Ambiguous numbers are omitted and named only as missing information. The proposal replaces the editor value but never creates a knowledge version until a person reviews it and explicitly saves it. Runs are rate-limited, budgeted, usage-recorded, and audited; questionnaire answers and raw provider responses are not retained. The final **Informasi yang Perlu Dikonfirmasi** section stays visible to people but is excluded from retrieval chunks, so unresolved material cannot become evidence for content generation.

## Owner decisions

| Decision | Answer |
|---|---|
| First brand | Prasetya Webworks |
| Approval | Not by the submitter of the revision (four-eyes) |
| Image | Single image, 4:5, 1080×1350 |
| Instagram URL when marking published | Optional; the actual posting time is required |
| AI providers (2026-11-08) | Copy through KelontongAI's OpenAI-compatible API; images uploaded manually |
| AI spending (2026-11-08) | No spending, weekly, per-revision, or per-day limit in the application, and no required prices; spending is controlled with the provider's own limit. Technical safeguards (one run per item, circuit breaker, timeouts) remain |
| Brand-rule severity (2026-11-08, proposed) | Forbidden words, claims, limits, and unapproved prices block submission; other findings warn |

Open until the phase that needs them: items per week and days (calendar does not enforce a count), fully generative versus brand assets for AI images, retention of drafts, research snapshots, generation logs and agent traces, OpenAI budget per run, week and brand, search and extraction provider, whether manual insights ship in the first release, where Hermes and the queue worker run in staging and production.

## Alternatives considered

- **Let the agent post directly through the Instagram API.** Rejected: publishing must be a human decision, and the API adds credentials and review requirements not needed for a weekly cadence.
- **Store content in Hermes or a separate content service.** Rejected: approval, versioning, and audit must live where the team already works and where they are tested.
- **Hard-wire OpenAI in the domain.** Rejected: provider prices, capabilities, and terms change; a contract per capability keeps the switch a configuration and evaluation step.
- **Approval by anyone, including the submitter.** Rejected by the owner in favour of four-eyes.

## Consequences

- At least two active users are needed to publish anything.
- Each AI phase adds an adapter and tests without touching the manual workflow; spending is limited at the provider, not in the application.
- AI phases need a queue worker and, for Hermes, a container host that the current cPanel server does not provide.
- Content history only grows: revisions, approvals, publications, and audit are never deleted by the workflow.
- Brand Knowledge changes are append-only and re-indexed into chunks on every saved version; content generation never needs to reinterpret or train on the raw document.
- AI-assisted Brand Knowledge remains optional: provider failure never blocks manual editing, and generated text has no authority until explicitly saved.
- Existing structured rules and facts remain compatible. Their statements and the active Brand Knowledge chunks both act as approved claim material while the legacy data is retained.
- Campaign records and URLs remain compatible while their management surface lives in Planning.

## Validation

- Feature tests for every state transition, four-eyes approval, revision immutability, stale-approval publishing refusal, upload validation by bytes, private download, audit, Brand Knowledge versioning/chunking/retrieval, and legacy structured validation.
- Architecture tests that `App\Domain\Content` does not use HTTP clients and that content code does not touch payment or order models.
- For AI phases: faked providers and contract tests, plus evaluation fixtures before enabling or switching a provider.
