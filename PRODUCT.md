# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Today the only user is the owner of Prasetya Group (confirmed 2026-11-14). They check orders and payments for Prasetya Webworks and Satamu Invitation, investigate and reconcile Duitku payments, and manage back-office users. In Content Studio they plan, write, mark ready, and manually post their own Instagram content (ADR-0012).

The product is built for a small internal team with one shared access level (ADR-0009), so more accounts may be added later; none are active yet.

Device use depends on the task (owner, 2026-11-14): transactions, reconciliation, and user management happen on a laptop; finishing and recording an Instagram post happens on a phone through the share sheet.

## Product Purpose

Prasetya Platform is the shared back office for two brands. It shows the true state of every order and payment, helps the team act on payments that need attention, and lets one person take Instagram content from an idea to a recorded post with AI help that never decides on its own. Success: the next action and the current state are clear on every screen, and nothing is marked paid, ready, or posted except by the evidence or the person the rules require.

## Positioning

An operational tool for the people who run the business, not a marketing site. It is built around backend authority: payment status comes only from verified gateway evidence, content status only from a person's action, and the screen never invents either.

## Operating Context

- Orders and checkouts arrive from the public Webworks and Satamu websites through the platform API; payments go through Duitku.
- Satamu packages are created inactive in every environment because the Satamu site's prices and content are still dummy; activating one is an audited catalogue change that waits for product approval (`docs/satamu-payment-activation-plan.md`).
- Content is posted to Instagram by hand; the platform prepares the package and records the post. There is no Instagram API.
- AI drafts content through an OpenAI-compatible provider and is optional: manual work never depends on it.
- Hosting is cPanel shared hosting today; the AI queue and Hermes need a separate host.

## Capabilities and Constraints

- Stack: Laravel, Inertia, Vue 3, TypeScript, Tailwind CSS, shadcn-vue with Reka UI. The redesign changes presentation only; domain rules, routes, and data contracts stay (see `docs/`).
- Statuses are always shown in words. Status labels are English; the rest of the interface is Indonesian.
- Short labels (page and section titles, navigation, buttons, field labels, table headers) use Title Case; sentences, hints, and errors use sentence case.
- Copy is professional and specific; shared text lives in `resources/js/lib/copy.ts`, labels and statuses in `resources/js/lib`.
- Every screen must work on mobile from 320 px without horizontal scrolling.
- Redesign pilot screen (owner, 2026-11-14): the transaction list, `/transactions` (Transaksi, view Semua).

## Brand Commitments

- Prasetya Webworks identity is binding: name, logo, and orange `#FF5100`. Do not recolour the Webworks or Satamu logos.
- Satamu Invitation keeps its own identity colour for its badge and chart series so the two brands stay distinguishable.
- Owner's binding visual constraints for the redesign (ADR-0014): white and orange carry the interface; red, green, and amber only for status and errors; shadow only on overlays; inputs with a thin border and no shadow; moderate font sizes; a collapsible sidebar and a mobile drawer; page title and subtitle in the page content, not in the top header; Heroicons as the only icon set.

## Evidence on Hand

- Real brand assets: Webworks and Satamu SVG marks in the source projects. The approved references in `docs/assets/` describe the current look and are superseded by the redesign.
- Demo data comes only from `DemoDataSeeder` and `ContentDemoSeeder`, built through the real actions. There are no customer testimonials, metrics, or case studies to show, and none may be invented.

## Product Principles

- The server decides: the interface presents state and reasons, it never assumes them.
- One next action per screen; rare or destructive actions sit behind a named menu and confirm first.
- Evidence is never deleted or hidden: history, audit, and provenance stay one named action away.
- AI assists and drafts; a person marks ready, records a post, and changes brand knowledge.

## Accessibility & Inclusion

WCAG 2.1 AA is a release requirement: contrast computed rather than guessed (ADR-0003), full keyboard operation with visible focus, dialogs that trap and return focus, labels on every field, status never by colour alone, respect for reduced motion, and 44 px touch targets. Browser tests run axe on every screen.

Known exception (owner, 2026-11-14): input, select, textarea, and outline-button borders use the sidebar hairline `#f0e3d9` (1.26:1 on white) instead of a 3:1 boundary. Each field keeps a visible label and the 2 px focus ring; checkboxes keep a 3:1 border. Revisit if a user reports trouble finding fields.
