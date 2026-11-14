# ADR-0013: Make the content detail workspace status-driven

- Status: Accepted (2026-11-14); implementation staged per the plan
- Date: 2026-11-14
- Decision owners: Prasetya Group
- Related: [`../content-detail-workspace-simplification-plan.md`](../content-detail-workspace-simplification-plan.md), [ADR-0012](0012-simplified-content-studio-workflow.md), [ADR-0011](0011-content-studio-authority-boundary.md), [`../design.md`](../design.md), [`../data-dictionary.md`](../data-dictionary.md)

## Context

ADR-0012 simplified the Content Studio lifecycle to Draft, Ready, Published, and Archived, and reduced the create form to an idea, a schedule, and an optional campaign. The content detail screen still presents most of the system's capabilities in one long workspace: schedule metadata, editor fields, AI controls, media, brand findings, lifecycle actions, posting package, research, AI runs, revision comparison, insights, and audit activity.

The current `Pengaturan tambahan` disclosure reduces the initial field count but remains a container for unrelated jobs. It also hides required alt text away from the image and the Ready action that depends on it. Ready and Published content continue to render the editor even though the operator's next task is respectively posting or recording performance. This makes the screen technically complete but harder to understand than the lifecycle it represents.

The product needs the detail route to answer one question at a time: what should the operator do next for this content? It must do so without removing revision evidence, research provenance, AI provenance, brand checks, publication evidence, insights, or audit history.

## Decision

1. **The content detail workspace is driven by the current status.** Draft opens a creation workspace, Ready opens a posting workspace, Published opens a performance workspace, and Archived opens a read-only summary.
2. **Only the next primary job is shown by default.** The initial screen must not render the full editor, posting package, insight form, research tools, revision comparison, and audit timeline together.
3. **Draft uses one visible production flow.** The primary Draft surface contains the brief, AI assistance, Instagram composition, media, alt text, brand-check summary, unsaved-state feedback, and readiness actions.
4. **Required information stays near the action that depends on it.** Alt text is placed with media instead of behind an advanced disclosure. Readiness communicates caption, media, alt text, brand-check, and saved-state requirements in words.
5. **Ready opens the posting package first.** Image preview, share/download/copy actions, planned time, and Mark as posted are primary. Editing is an explicit secondary action that explains the Ready-to-Draft consequence before exposing the editor.
6. **Published opens performance first.** Publication evidence, Instagram link, content snapshot, and insight recording are primary. Editing controls are not shown.
7. **Archived is read-only.** The screen shows the last content snapshot, archival context when available, and evidence links without editor, AI, posting, or lifecycle controls.
8. **Secondary capabilities become named contextual surfaces.** Edit plan, Research and sources, Version history, and Activity are separate sheets, dialogs, or focused views. A single catch-all `Pengaturan tambahan` disclosure is not used.
9. **AI is presented as assistance, not an engineering console.** The default Draft view has one Generate/Regenerate action. Field selection and an optional instruction are disclosed on demand; provider, model, prompt version, and run history live in history/evidence surfaces.
10. **Brand findings use progressive disclosure.** A clean result is one success summary. Warnings are summarized and expandable. Blocking errors are expanded near readiness and keep Ready unavailable.
11. **Secondary data may be lazy-loaded.** Research runs and sources, full revision history, AI run history, audit activity, and status-irrelevant insights should not be required for the initial response. Authorization and server-side scoping remain identical when they are loaded.
12. **The domain contract does not change.** Status transitions, revision immutability, Ready-to-Draft on edit, deterministic brand checks, human-only Ready/Published/Archived transitions, publication evidence, AI authority boundaries, and audit requirements remain governed by ADR-0011 and ADR-0012.
13. **The existing canonical detail URL remains stable.** `/content/items/{id}` continues to resolve the item workspace. Secondary surfaces may use query state or focused endpoints, but old bookmarks must remain valid.
14. **Implementation is staged and reversible.** Presentation and loading changes ship behind tests without deleting data, tables, actions, or evidence. The previous Vue composition can be restored without a data rollback.

## Alternatives considered

- **Keep the current page and improve spacing.** Rejected because the problem is competing jobs and information hierarchy, not only density or visual styling.
- **Put everything into tabs that are always visible.** Rejected as the default because a fixed set of many tabs still asks every operator to understand research, revisions, audit, and insights before acting. Named secondary actions may open focused views, but the primary surface remains status-driven.
- **Keep one large advanced disclosure.** Rejected because it groups unrelated capabilities, grows without a clear boundary, and hides required alt text away from media and readiness.
- **Remove research, revisions, AI history, and audit.** Rejected because they provide provenance, troubleshooting, and compliance evidence even when they are not part of the routine flow.
- **Create a different URL for every lifecycle status.** Rejected because status can change during the workflow and a stable canonical item URL is easier to link, audit, and maintain.
- **Allow inline editing while Ready without returning to Draft.** Rejected because it would violate revision immutability and make the posting package differ from the revision marked Ready.

## Consequences

- The routine Draft, Ready, Published, and Archived experiences become materially smaller and more predictable.
- Vue components are split by user job rather than by database entity, which adds component boundaries but reduces conditional complexity inside one giant editor.
- The controller/presenter may need lazy or partial Inertia props and focused authorized endpoints for secondary surfaces.
- Existing E2E selectors and tests that assume one long page must be rewritten around status-specific workspaces.
- Research, history, and audit remain discoverable but require one explicit action to open.
- Product copy must clearly explain that editing Ready content creates a new revision and returns it to Draft.
- The initial detail response should become smaller, but lazy surfaces add subsequent requests that need loading, error, and retry states.

## Implementation notes (2026-11-14)

- Secondary evidence loads from three read-only JSON endpoints (`GET /content/items/{id}/research`, `/history`, `/activity`) rather than Inertia deferred or partial-reload props. In this application a 500 response renders the Inertia error page, so a failed partial reload would replace the workspace and lose unsaved Draft edits; a failed `fetch` stays inside its sheet with a retry. The endpoints sit behind the same `auth` and active-user middleware and resolve the item by public id.
- Research is read-only in the UI outside Draft. The research actions still accept a Ready item as before; changing that would be a domain decision, not part of this presentation change.
- *Kembali ke Draft* without editing stays available on Ready from the header menu, so no existing capability disappears.

## Validation

- Browser tests cover Draft creation and editing, AI generation, image and alt text, brand findings, save, Ready, posting, Published insight, archive, and Ready-to-Draft editing.
- Component tests prove that each status renders only its relevant primary workspace and that required secondary capabilities remain reachable.
- Feature tests prove lazy endpoints preserve authentication, authorization, item scoping, immutable evidence, and the existing state machine.
- Accessibility checks cover keyboard navigation, sheet/dialog focus management, 200% zoom, reduced motion, and WCAG A/AA on every status workspace.
- Baseline and post-change measurements compare visible controls, initial payload, query count, page responsiveness, and time/interaction count for Draft-to-Ready and Ready-to-Published tasks.
- Rollback rehearsal restores the previous Vue page composition without database or status migration.
