# Back-office Redesign: Migration Tracker

- Status: In progress (pilot merged in PR #5; Masuk migrated)
- Decision: [ADR-0014](decisions/0014-back-office-redesign.md)
- Visual system: [`../DESIGN.md`](../DESIGN.md) (tokens and rules; this file does not repeat them)
- Product context: [`../PRODUCT.md`](../PRODUCT.md)

ADR-0014 moves the back office to the Prasetya Webworks identity one screen at a time. This file records which screens have moved, how to move the next one, and the owner's decisions made along the way, so a later change can tell a deliberate choice from drift.

## What every screen already has

These ship once, for every screen, whether the screen itself has been migrated or not:

- Tokens in `resources/css/app.css`: white ground, the tint ramp, Overpass and Overpass Mono, the overlay-only shadow, and one light theme regardless of the OS colour scheme.
- The app shell: the station sidebar (collapsible on desktop, drawer below `lg`), a header with navigation and account only, and the page title and subtitle in the content.
- Shared parts restyled through tokens: `DataTable`, `TablePagination`, `FilterBar`, `SelectFilter`, `SearchInput`, `DateRangeFilter`, `StatusBadge`, `BrandBadge`, `PasswordInput`, and the shadcn input, select, textarea, checkbox, and outline button without shadows.
- English labels for payment, order, and webhook statuses (`resources/js/lib/status.ts`).

An unmigrated screen therefore already looks close. What is left on it is its own composition: cards and panels from the old look, Lucide icons, and any layout that the new rules do not allow.

## Screen status

| Screen | Route | Status | Left to do |
|---|---|---|---|
| Masuk | `/login` | Migrated | None |
| Error pages, signed out | any | Migrated | None |
| Ganti Kata Sandi | `/account/password` | Migrated (new, ADR-0015) | None |
| Transaksi, Semua | `/transactions` | Migrated (pilot) | None |
| Transaksi, Perlu Ditinjau | `/transactions?view=attention` | Migrated (four queues, ADR-0016) | `GatewayCheckDialog` still uses the stock outline button |
| Pengaturan, Log Gateway | `/settings/gateway-log` (was Transaksi → Event Pembayaran) | Shell only; moved out of Transaksi (ADR-0016) | `PanelCard` around the webhook table; webhook table composition; diagnostics panel |
| Detail transaksi | `/transactions/{id}` | Migrated (single page, ADR-0016) | Admin note textarea uses the stock `Button` |
| Dashboard | `/dashboard` | Migrated | None |
| Pengguna | `/users`, `/users/{id}` | Shell only | Lucide in row actions and create dialog; detail layout |
| Pengaturan, Aktivitas Sistem | `/settings`, `/settings/activity` | Shell only; profile and password forms moved out (ADR-0015) | Lucide; `PanelCard` admin panels |
| Audit Log | `/audit-log` | Shell only | Table and filters review |
| Error pages, signed in | any | Shell only | Lucide icons; `PanelCard` around the empty state |
| Content Studio: Kalender, Brand, Insight, detail | `/content/*` | Migrated (ADR-0017) | `ContentCreateDialog`, `CampaignDialog`, and `BrandKnowledgeAssistantDialog` keep stock dialog styling |
| Pengaturan, Model AI | `/settings/ai` (was Content Studio → Pengaturan AI) | Migrated (ADR-0017) | None |

"Shell only" means the screen sits in the new shell and inherits tokens and shared parts, but its own layout has not been redesigned.

## How to migrate a screen

1. Read `DESIGN.md` and this file. The world is settled: no new direction round, no new colours, fonts, or shadows.
2. Keep routes, props, and data contracts. A behaviour change needs its own ADR.
3. Replace `PanelCard` boxes with flat tint fields or plain sections (`PageSection` has the same props and slots); nothing but overlays gets a shadow.
4. Replace Lucide imports with `@heroicons/vue/24/outline` (`stroke-2`). Remove `@lucide/vue` from `package.json` once no file imports it.
5. Use English for every status label and Indonesian for everything else; Title Case for short labels only.
6. Check the screen at 320, 360, 768, 1280, and 1440 px: no horizontal scroll, 44 px touch targets, visible focus.
7. Run `npm run lint`, `npm run typecheck`, `npm test`, and the Playwright suite (axe included), then update the table above in the same change.

## Owner decisions during the redesign

| Date | Decision | Where it lives |
|---|---|---|
| 2026-11-14 | Direction "Rambu Jalur Commuter" chosen for the pilot from three cards | `DESIGN.md`, `.impeccable/surfaces/` |
| 2026-11-14 | The header carries navigation and account only; the global search is removed, search lives on Transaksi | `AppHeader.vue`, ADR-0014 §6 |
| 2026-11-14 | Sidebar stations stand apart: no connecting line between menu items | `AppSidebar.vue`, `DESIGN.md` Navigation |
| 2026-11-14 | One light theme: the OS dark mode never tints inputs | `app.css` (`@custom-variant dark`, `color-scheme: light`) |
| 2026-11-14 | Field borders use the sidebar hairline `#f0e3d9` (1.26:1), an exception to the 3:1 boundary rule; checkboxes keep `#c47f55` (3.22:1) | `PRODUCT.md` Accessibility, `DESIGN.md` Colors |
| 2026-11-14 | Payment method codes are labelled only as far as the Duitku contract confirms, for example `QRIS (SP)` | `resources/js/lib/payment-methods.ts` |
| 2026-11-14 | Login panel copy: "Kelola operasional bisnis dari satu tempat." | `resources/js/lib/copy.ts` |
| 2026-11-14 | Profile and settings separated: no self-service profile; names change in Pengguna; own password on Ganti Kata Sandi from the account menu | [ADR-0015](decisions/0015-account-password-page.md) |
| 2026-11-14 | Dashboard layout "Rute sebagai Tulang Punggung" chosen from three structures: the payment route at page scale leads, Pending as the hub | `DESIGN.md` Dashboard, `.impeccable/surfaces/` |
| 2026-11-14 | Indonesian messages for Laravel's built-in validation rules (raw keys such as `validation.current_password` no longer leak) | `lang/id/validation.php` |

## Known gaps

These are recorded rather than fixed, so they are not mistaken for intended design:

- Native date inputs still show the browser's own `dd/mm/yyyy` and calendar glyph.
- The shadcn `Button` and `Input` defaults are 36 px (`h-9`); migrated screens set 44 px themselves. Raise the defaults once every screen has moved.
- The `link` button variant uses `text-primary` (`#FF5100` as text, 3.27:1). Nothing uses it yet; switch it to `text-primary-text` before first use.
- Stock overlays (select, dropdown, sheet) use `shadow-md` and `shadow-lg` instead of `--shadow-overlay`.
- `ContentBrandPage.vue` has a thick accent border on a rounded element, from the old look.
