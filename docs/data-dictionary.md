# Data dictionary

MySQL 8. All timestamps UTC. Money is unsigned integer rupiah. `public_id` is a ULID; numeric ids never leave the server.

> Content Studio follows [ADR-0012](decisions/0012-simplified-content-studio-workflow.md) since phase 1 of the [simplification plan](content-studio-simplification-plan.md): `DRAFT`, `READY`, `PUBLISHED`, and `ARCHIVED`, with ADR-0011 approval data kept as read-only legacy evidence; see *Content item lifecycle* below.

## Tables

| Table | Purpose | Key rules |
|---|---|---|
| `applications` | Brands: `WEBWORKS`, `SATAMU` | Unique `code`, `invoice_prefix` |
| `users` | Back-office accounts | `username` unique, lower-case, fixed at creation, used to sign in ([ADR-0009](decisions/0009-single-access-users-username-login.md)). `email` is nullable, hidden, and unused. No roles: every active user has full access. Inactive users cannot sign in. There is no `password_reset_tokens` table: passwords are set by another user or `platform:reset-password` |
| `customers` | Customer per brand | Unique (`application_id`, `email`). Never overwritten by public input |
| `packages` | Authoritative catalogue | Unique stable `code`, `price_amount`. `is_active` is catalogue state: set once when the seeder creates a package (Satamu starts `false`) and changed afterwards only by `SetPackageAvailability`, which writes an audit entry. Inactive packages cannot be ordered; their existing orders and payments are unaffected |
| `invoice_sequences` | Per-brand, per-month counter | Unique (`application_id`, `period`), row-locked |
| `orders` | One purchase | Unique `invoice_number`, own `status`, snapshot of customer contact |
| `order_items` | Package snapshot | Code, name, and price copied at order time |
| `order_status_histories` | Append-only order transitions | Source, actor, reason, request id |
| `admin_notes` | Internal notes | Writing one is audited |
| `webworks_order_details` | Template, business, domain, notes | One per order |
| `invitation_order_details` | Theme, event type and date, notes | One per order |
| `payments` | One checkout attempt | Unique (`order_id`, `attempt_number`), `gateway_invoice_number`, `gateway_request_id`, `gateway_reference` |
| `payment_events` | Append-only timeline per attempt | Unique (`webhook_event_id`, `type`) |
| `webhook_events` | Evidence of each delivery | `primary_dedupe_key` unique on first delivery only |
| `idempotency_keys` | Public mutation guard | Unique (`scope`, `idempotency_key`); stores a fingerprint and a resource reference |
| `audit_logs` | Append-only record of sensitive actions | Actor, action, entity, previous and new state, reason, request id. User changes: `user.created`, `user.updated`, `user.activated`, `user.deactivated`, `user.password_reset`, never with a password. Shown by entity ([ADR-0010](decisions/0010-consolidated-transaction-workspace.md), `AuditTrail`): order and payment on the transaction detail; any entry about a user on that user's page; everything else, including sign-in, profile, and packages, under Pengaturan → Aktivitas Sistem, which can also search every entry |
| `content_brands` | Brands Content Studio plans for ([ADR-0011](decisions/0011-content-studio-authority-boundary.md)) | Unique `code`, name, `is_active`. Brand knowledge (audience, voice, facts) arrives in phase 2 |
| `content_items` | One planned single-image Instagram post | Brand, title, `planned_at` (UTC), status (lifecycle below), `active_revision_id`, `ready_revision_id` (the revision marked Ready; kept on `PUBLISHED`), `approved_revision_id` (legacy ADR-0011 evidence, never set for new items), creator |
| `content_revisions` | One version of an item's content | Unique (`content_item_id`, `number`). Objective, audience, brief, caption, hashtags, alt text, visual direction, author. **Immutable once `submitted_at` is set** (marked Ready, or submitted under ADR-0011); any later change is a new revision |
| `content_assets` | The image of a revision | One per revision. Private disk, path by SHA-256 checksum (deduplicated), MIME type from the bytes, size, width, height, original name, uploader |
| `content_approvals` | Legacy review decisions (ADR-0011), read-only | Revision, decision (`APPROVED`, `REVISION_REQUESTED`, `REJECTED`), `areas`, note, actor, time. No new rows since ADR-0012; never created by migration or rollback |
| `content_publications` | The human statement that an item was posted | One per item. Revision posted, actual `published_at` (required), Instagram URL (optional), note, `checklist` (posting steps a person confirmed; required under ADR-0011, optional and usually null since ADR-0012 phase 4, kept as evidence), actor. Immutable |
| `content_brand_profiles` | Legacy structured brand rules (phase 2 compatibility) | Append-only; unique (`content_brand_id`, `version`). Audience, voice, visual guidance, bounded lists, caption/hashtag limits, topic repeat days, change note, and author. Existing revisions continue to reference the version they met. It is no longer the primary user-facing Brand editor |
| `content_brand_knowledge_versions` | One free-text Brand Knowledge document per saved version | Append-only; unique (`content_brand_id`, `version`). `source_text`, author, and creation time. `content_brands.current_knowledge_version_id` points at the active version. Saving identical text is refused |
| `content_brand_knowledge_chunks` | Retrieval units derived from one knowledge version | Ordered by `position`, maximum 100 per version and approximately 1,200 characters per chunk. The final Markdown section `Informasi yang Perlu Dikonfirmasi` remains in `source_text` but is deliberately omitted here. The first implementation performs deterministic keyword ranking; this row is the future attachment point for an embedding without changing the source document |
| `content_brand_knowledge_assists` | Operational records for the Brand Knowledge AI interview | Brand, requester, provider/model, status, prompt version, token usage, estimated cost, latency, safe error, and timestamps. Questionnaire answers and raw provider output are not stored |
| `content_pillars` | Recurring themes of a brand | Unique (`content_brand_id`, `name`). Description, `target_share` (percent of a week's posts; active pillars total at most 100), `is_active` |
| `content_campaigns` | Time-boxed pushes | Name, objective, `starts_on`/`ends_on` (business-time days, inclusive). Whether one runs is derived from the period in WIB (ADR-0012); only running campaigns are accepted for a new item. `priority` (`HIGH`, `NORMAL`, `LOW`) and `is_active` are legacy: defaulted to `NORMAL` and true, kept unchanged when not sent, and a manual `false` still hides a campaign |
| `content_facts` | Approved Knowledge Base entries content may state | Controlled `category` (`GENERAL`, `SERVICE`, `PRICE`, `PROCESS`, `ADVANTAGE`, `FAQ`, `PROMO`, `POLICY`, `EVIDENCE`), statement, source, optional campaign, optional `valid_until`. Existing rows migrate as `GENERAL`. The statement and category never change; correction retires it (`retired_at`, by, reason) and adds a new entry |
| `content_research_runs` | One web search (phase 4) | Brand, item, query, status, provider, kept and dropped counts, the sources it returned, search calls, estimated `cost_usd`, latency, safe error |
| `content_research_sources` | Research material per brand | Unique per brand by URL identity (canonical, no tracking parameters, `www.` ignored). Origin (`SEARCH`, `MANUAL`), URL, domain, title, summary (at most 600 characters, never the article), trust (`OFFICIAL`, `GENERAL`, `USER_GENERATED`), relevance, published date, access time, who added it |
| `content_item_sources` | Citations | Item, source, team note, who linked it, when. At most 8 per item |
| `content_insight_snapshots` | Instagram metrics read by hand (phase 8) | Item, `measured_at` (not before posting, not in the future), reach (required), views, likes, comments, saves, shares, profile visits, follows (optional, non-negative), note, who recorded it. Append-only; summaries use each post's latest snapshot. Engagement = (likes + comments + saves + shares) ÷ reach |
| `content_ai_settings` | Choices made in Pengaturan AI | Key and value, e.g. `copy_model`: one of the models the server lists in `CONTENT_TEXT_MODELS`. Never a key or secret |
| `content_generation_runs` | One AI copy generation (phase 3) | Item, base revision, result revision, requester, requested fields, status (`QUEUED`, `RUNNING`, `SUCCEEDED`, `FAILED`, `DISCARDED`), provider, model, prompt version, input and output tokens, estimated `cost_usd`, latency, attempts, safe error code and message, provider request id. Phase 6: the writer's `instruction`, `used_review_note`, and people's `feedback` (`USEFUL`, `NOT_USEFUL`) with reason, author, and time. No prompt or raw response is stored |

Phase 2 also adds `content_items.content_pillar_id`, `content_campaign_id`, and `topic` (nullable plan details), and `content_revisions.brand_profile_id`: the rules version a revision met when it was submitted. Phase 3 adds `content_revisions.generation_run_id` on revisions AI drafted.

### Content item lifecycle (ADR-0012)

```text
DRAFT <-> READY -> PUBLISHED
  |         |
  +---------+-> ARCHIVED
```

- `READY` is a person's completion marker, not an approval. It needs the active revision with caption, alt text, and a valid image, and no blocking brand finding. Warnings do not block.
- Editing a `READY` item creates a new revision, makes it active, and returns the item to `DRAFT`.
- `READY` → `DRAFT` (**Kembali ke Draft**) keeps the frozen revision; marking it Ready again does not refreeze it.
- `PUBLISHED` comes only from a person recording an actual posting time (URL and note optional) on the current Ready revision, not before it was marked Ready. No checklist is required (phase 4).
- `ARCHIVED` (from `DRAFT` or `READY`, optional reason in audit `content.archived`) keeps every revision, decision, publication, source, AI run, and audit entry.
- AI, queues, schedulers, and Hermes may only create or revise `DRAFT` content; a run that finishes after the item left Draft is discarded. A passed planned time changes nothing.
- Audit actions: `content.marked_ready`, `content.returned_to_draft`, `content.archived`, `content.revised` (with previous and new status), `content.published`.
- Migration mapping (`App\Domain\Content\Migration\LegacyStatusMap`): `DRAFT`→`DRAFT`, `READY_FOR_REVIEW`→`DRAFT`, `REVISION_REQUESTED`→`DRAFT`, `APPROVED`→`READY`, `PUBLISHED`→`PUBLISHED`, `REJECTED`→`ARCHIVED`. Rollback: `READY`→`APPROVED` and `ARCHIVED`→`REJECTED` as statuses only; no approval row is ever created.
- Migration `2026_11_09_000001_simplify_content_lifecycle` adds `ready_revision_id`, then `LegacyStatusBackfill` maps statuses (idempotent). An `APPROVED` item becomes Ready on its active revision when that revision is frozen and no approval of another revision contradicts it; one approved on an older revision stays without `ready_revision_id`, cannot be posted, and is reported by the audit.
- Compatibility: `content_approvals`, `approved_revision_id` (kept on `PUBLISHED` items as evidence of the posted revision), decision areas, and publication checklists stay as read-only legacy evidence. `php artisan content:status-audit` checks the data before and after; it also reads a rolled-back schema.

### Brand rule check

The user-facing Brand screen stores one free-text document. It is not model training. On save, Laravel versions and chunks it deterministically. Copy generation ranks chunks against the content title, topic, objective, audience, and brief; at most six relevant chunks enter prompt `brand_knowledge`. For short documents all chunks are included. Existing structured brand profiles and facts remain active for deterministic rules and compatibility during the transition.

`App\Domain\Content\Support\ContentRuleValidator` checks a revision against stored data only; the same input gives the same findings. Public text is the caption, hashtags, and alt text.

| Finding | Severity |
|---|---|
| Forbidden term or claim (whole word, case-insensitive) | Error |
| Caption over the brand maximum, hashtags over the maximum, forbidden hashtag | Error |
| A price (`Rp …`) that no usable fact states (not retired, not expired on the planned day, brand-wide or the item's campaign) | Error |
| Caption under the minimum, too few hashtags, required hashtag missing, no approved CTA | Warning |
| A percentage no usable fact states | Warning |
| Inactive pillar or campaign, planned outside the campaign period, the same topic within the repeat window | Warning |
| No brand rules yet | Warning |

Errors block submission for review and an edit of approved content; warnings are shown to writer and reviewer.
