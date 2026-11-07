# Data dictionary

MySQL 8. All timestamps UTC. Money is unsigned integer rupiah. `public_id` is a ULID; numeric ids never leave the server.

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
| `content_items` | One planned single-image Instagram post | Brand, title, `planned_at` (UTC), status (state machine below), `active_revision_id`, `approved_revision_id` (null unless the current revision is approved), creator |
| `content_revisions` | One version of an item's content | Unique (`content_item_id`, `number`). Objective, audience, brief, caption, hashtags, alt text, visual direction, author. **Immutable once `submitted_at` is set**; any later change is a new revision |
| `content_assets` | The image of a revision | One per revision. Private disk, path by SHA-256 checksum (deduplicated), MIME type from the bytes, size, width, height, original name, uploader |
| `content_approvals` | Append-only review decisions | Revision, decision (`APPROVED`, `REVISION_REQUESTED`, `REJECTED`), note, actor, time. An approval names exactly one revision |
| `content_publications` | The human statement that an item was posted | One per item. Revision posted, actual `published_at` (required), Instagram URL (optional), note, actor. Immutable |
| `content_brand_profiles` | Versioned brand rules (phase 2) | Append-only; unique (`content_brand_id`, `version`). Audience, voice, visual guidance; bounded JSON lists of short phrases (preferred and forbidden terms, forbidden claims, approved CTAs, required and forbidden hashtags, approved and rejected examples); caption length and hashtag limits; topic repeat days; change note, author. `content_brands.current_profile_id` points at the version in force |
| `content_pillars` | Recurring themes of a brand | Unique (`content_brand_id`, `name`). Description, `target_share` (percent of a week's posts; active pillars total at most 100), `is_active` |
| `content_campaigns` | Time-boxed pushes | Name, objective, `starts_on`/`ends_on` (business-time days, inclusive), priority (`HIGH`, `NORMAL`, `LOW`), `is_active` |
| `content_facts` | Approved business facts content may state | Statement, source, optional campaign, optional `valid_until`. The statement never changes; correction retires it (`retired_at`, by, reason) and adds a new fact |

Phase 2 also adds `content_items.content_pillar_id`, `content_campaign_id`, and `topic` (nullable plan details), and `content_revisions.brand_profile_id`: the rules version a revision met when it was submitted.

### Brand rule check

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
