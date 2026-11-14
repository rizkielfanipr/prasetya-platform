# ADR-0014: Redesign the back office on the Prasetya Webworks identity

- Status: Proposed
- Date: 2026-11-14
- Decision owners: Prasetya Group
- Related: [`../design.md`](../design.md), [`../../PRODUCT.md`](../../PRODUCT.md), [ADR-0003](0003-accessible-colour-tokens.md), [ADR-0009](0009-single-access-users-username-login.md), [ADR-0013](0013-status-driven-content-detail-workspace.md)
- Supersedes when accepted and shipped: the visual sections of `design.md` (visual direction, colour tokens other than the derived accessible ones, typography scale, app shell, header)

## Context

The back office uses a navy sidebar, warm ivory canvas, Raleway headings up to 32 px, Lucide icons, and Indonesian status labels. The owner wants a redesign rather than a refinement: a lighter, more conventional operational layout that keeps the Prasetya Webworks identity and reads as professional software. The domain, routes, permissions, and data contracts are not part of this decision.

## Decision

Binding constraints from the owner (2026-11-14), to be carried into `PRODUCT.md` and the new `DESIGN.md`:

1. **Identity.** The Prasetya Webworks identity stays: name, logo, and the Webworks orange `#FF5100`. Satamu Invitation keeps its own badge and chart colour so data of the two brands stays distinguishable.
2. **Palette.** White and variations of orange carry the interface. Body text stays a dark neutral, because orange on white does not reach WCAG AA for normal text; orange used as text or under white text keeps a derived accessible token (ADR-0003). Red, green, and amber remain only for status badges, errors, and warnings, always with a word.
3. **Elevation.** Shadow is rare: overlays (dialogs, sheets, menus) only. Cards, tables, and inputs have none.
4. **Forms.** Every input, select, and textarea uses a thin border and no shadow.
5. **Typography.** Sizes stay moderate; no oversized page titles. The scale is set in the new `DESIGN.md`.
6. **App shell.** The sidebar collapses and expands on desktop and becomes a drawer on mobile. The top header carries navigation and account controls only: the page title and subtitle live in the page content, not in the header.
7. **Icons.** Heroicons (`@heroicons/vue`) replace Lucide across the application, in one style family.
8. **Layout.** Industry-standard operational layout: consistent page header in content, aligned grids, a designed mobile version of every screen, no horizontal overflow from 320 px.
9. **Copy.** Indonesian, professional, and specific. Short labels (page and section titles, navigation, buttons, field labels, table headers) use Title Case; sentences, hints, and error messages use sentence case.
10. **Statuses in English.** Every status label is English (for example Draft, Ready, Published, Archived; Paid, Pending, Failed, Expired), still shown in words and never by colour alone.

Process:

- The new visual direction is chosen with Impeccable's new-work flow on one pilot screen, the transaction list (`/transactions`, owner's choice 2026-11-14), and recorded as `DESIGN.md` at the project root. The current look is reference for what to leave, not a base to polish.
- Tokens stay in `resources/css/app.css` as semantic variables, so components move with the tokens rather than by copied classes.
- Screens move one at a time after the pilot is approved; each keeps its tests, keyboard and axe checks, and responsive checks green.
- When the redesign ships, `design.md` points to `DESIGN.md` for visual decisions, so there is one source.

## Alternatives considered

- **Refine the current look.** Rejected by the owner: the goal is a new visual direction, not polish on the old one.
- **Keep Lucide.** Rejected by the owner in favour of Heroicons for a more conventional icon set.
- **Indonesian status labels.** Rejected by the owner: statuses become English terms, the rest of the copy stays Indonesian.
- **Title Case for all text.** Rejected: long sentences and Indonesian prose read worse in Title Case; it applies to short labels only.
- **No status colours at all.** Rejected: errors and failures need a recognisable signal beyond words for fast scanning.

## Consequences

- `@heroicons/vue` is a new dependency; `@lucide/vue` is removed once no screen uses it. Every icon import changes.
- Status labels in `resources/js/lib/status.ts` and `resources/js/lib/content.ts` change, and so do tests and E2E selectors that match Indonesian status text.
- `AppHeader`, `PageHeader`, `AppSidebar`, and `AppLayout` change shape; every page gains an in-content page header.
- ADR-0003's derived tokens are re-checked against the new palette with the contrast checker before use.
- Copy across the application is reviewed once for tone and capitalisation; `resources/js/lib/copy.ts` stays the single home of shared text.
- Shared `DataTable` and `TablePagination` are restyled through tokens, not rewritten.

## Validation

- The pilot screen is reviewed by the owner at 320, 360, 768, 1280, and 1440 px before any other screen moves.
- Contrast is computed for every text and non-text pairing of the new palette (AA: 4.5:1 normal text, 3:1 large text and UI boundaries).
- Existing Pest, Vitest, and Playwright suites stay green, including axe WCAG A/AA, keyboard, reduced motion, and the 320 to 1440 px overflow sweep.
- No domain, route, or data contract changes ship under this ADR.
