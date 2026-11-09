# ADR-0012: Simplify Content Studio to a creator-owned workflow

- Status: Proposed
- Date: 2026-11-08
- Decision owners: Prasetya Group
- Related: [`../content-studio-simplification-plan.md`](../content-studio-simplification-plan.md), [ADR-0011](0011-content-studio-authority-boundary.md), [`../design.md`](../design.md), [`../data-dictionary.md`](../data-dictionary.md)
- Supersedes when implemented: ADR-0011 decisions 6–8 and its four-eyes owner decision

## Context

Content Studio is currently operated as a multi-person editorial workflow: a revision is submitted, another active user reviews it, and only an approved revision can be recorded as published. The intended day-to-day operator instead plans, edits, and manually posts their own Instagram content. Four-eyes approval, separate Calendar, Content, and Ready-to-post pages, seven copy fields, and a mandatory posting checklist add ceremony without reducing risk proportionally for this owner-operated workflow.

AI still cannot be trusted to publish, change authoritative brand knowledge, or turn an item into a public fact. Manual Instagram posting remains the real external control. The product therefore needs a smaller workflow without discarding revision history, audit, research sources, or existing approval evidence.

## Decision

1. **The operator owns the content lifecycle.** There is no submit, approve, reject, or request-revision step in the target workflow. A signed-in person may move their content between Draft and Ready to post, archive it, and record that it was posted.
2. **The target lifecycle has four user-facing states:** `DRAFT`, `READY`, `PUBLISHED`, and `ARCHIVED`. The allowed transitions are `DRAFT <-> READY`, `DRAFT|READY -> ARCHIVED`, and `READY -> PUBLISHED`. Published and archived items are terminal in the normal UI.
3. **Ready is not approval.** It is an explicit personal completion marker. AI, Hermes, queues, and schedulers cannot set `READY`, `PUBLISHED`, or `ARCHIVED`. They may only create or revise a draft.
4. **Brand checks remain deterministic.** Blocking brand-rule errors prevent `READY`; warnings inform but do not block. A content edit after Ready creates a new revision and returns the item to Draft automatically.
5. **Calendar becomes the Content Studio workspace.** Calendar owns planning, status filters, creation, and entry into the content workspace. Separate Content and Ready-to-post navigation is removed; their capabilities become Calendar views and filters. Existing URLs redirect during migration.
6. **Creation asks only for intent, schedule, and optional campaign.** Topic/idea and planned time are required. Campaign appears only when an active campaign exists. Brand is implicit when there is one brand. Pillar, title, audience, objective, and initial brief are inferred or defaulted and remain editable under advanced settings.
7. **The main editor shows one brief, the caption, and the image.** Hashtags remain separately stored but visually attached to the caption. Audience, objective, visual direction, alt text, pillar, sources, and history move to contextual or collapsed advanced sections. Removing a field from the primary UI does not remove its data or validation contract.
8. **Campaigns have a minimal primary form:** name, inclusive period, and objective. Active state is derived from the period. Priority and manual activation remain readable for legacy rows during migration but are not required for new campaigns.
9. **Posting stays manual.** The posting package provides preview, copy caption, download/share image, and Mark as posted. Recording publication requires actual time and accepts an optional Instagram URL. A multi-step evidence checklist is not required.
10. **History is retained.** Existing revisions, approvals, decisions, publication evidence, research, generation runs, and audit rows are not deleted or rewritten. Historical approval data becomes read-only legacy evidence.
11. **Weekly AI planning remains reviewable selection.** AI may propose ideas from Brand Knowledge, active campaigns, recent content, research, and pillar balance. A person selects proposals before they become Draft items. This selection is planning, not editorial approval.
12. **Implementation is staged.** The proposed state machine is not authoritative until its migration and compatibility tests ship. Until then, ADR-0011 describes runtime behaviour and this ADR describes the accepted target once its status is changed to Accepted.

## Alternatives considered

- **Keep four-eyes approval.** Rejected for the target workflow because it requires a second user for an owner-operated process and makes routine editing disproportionately expensive.
- **Let AI automatically mark items Ready.** Rejected because Ready authorizes the operator to use the posting package; a person must make that completion decision.
- **Remove revisions and audit together with approval.** Rejected because change history, AI provenance, research evidence, and troubleshooting remain valuable even without an approval gate.
- **Keep every field visible but make it optional.** Rejected because optional fields still create cognitive load and obscure the three inputs needed to start.
- **Automatically publish at the scheduled time.** Rejected because there is no Instagram API in scope and a planned time is not evidence that a post exists.

## Consequences

- A single operator can take an idea from planning to a recorded post without waiting for another account.
- The primary UI becomes smaller while the underlying content record remains expressive for AI, analytics, and future multi-user workflows.
- `content_approvals`, old status values, `approved_revision_id`, and checklist evidence need an explicit compatibility and rollback strategy; they cannot be dropped in the first migration.
- Product metrics shift from approval/rejection rates to draft-to-ready time, ready-to-posted conversion, AI draft usefulness, and posting timeliness.
- Routes, status labels, domain actions, policies, presenters, tests, runbooks, and Hermes tool schemas must change together.
- Removing the editorial gate makes Brand Knowledge quality and deterministic blocking checks more important.

## Validation

- Feature tests cover every new transition, AI inability to set Ready/Published/Archived, automatic Ready-to-Draft on edit, blocking brand errors, legacy status mapping, redirects, and immutable historical evidence.
- Browser tests cover create with three inputs, AI draft, edit, Ready, posting package, Mark as posted, archive, responsive Calendar filters, keyboard operation, and WCAG A/AA.
- Migration tests prove existing approved items become Ready, review/revision-request items become Draft, rejected items become Archived, and all approvals remain queryable.
- Rollback is demonstrated on a production-like database copy before deployment.
