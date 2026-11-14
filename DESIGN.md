---
name: Prasetya Platform
description: Shared back office for Prasetya Webworks and Satamu Invitation, set as a commuter-line signboard.
colors:
  line-orange: "#ff5100"
  line-orange-deep: "#c93f00"
  line-orange-deep-hover: "#ad3600"
  accent-text: "#b83a00"
  tint-50: "#fffaf6"
  tint-100: "#fff3ea"
  tint-200: "#ffe6d5"
  tint-300: "#ffd2b8"
  tint-400: "#ffb48f"
  tint-500: "#ff8a57"
  tint-800: "#9a3000"
  enamel-white: "#ffffff"
  ink: "#1b1f24"
  sign-ink: "#3a4048"
  muted-ink: "#5f6670"
  hairline: "#f0e3d9"
  input-border: "#f0e3d9"
  checkbox-border: "#c47f55"
  satamu-coral: "#ff6542"
  satamu-text: "#b3341a"
  satamu-surface: "#ffece7"
  chart-satamu: "#ff8f75"
  chart-grid: "#f1ebe5"
  success: "#15803d"
  success-surface: "#dcfce7"
  warning: "#b45309"
  warning-surface: "#fef3c7"
  danger: "#b91c1c"
  danger-surface: "#fee2e2"
  info: "#1d4ed8"
  info-surface: "#dbeafe"
  neutral: "#525252"
  neutral-surface: "#f1efed"
typography:
  title:
    fontFamily: "Overpass Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "24px"
    fontWeight: 800
    lineHeight: 1.25
    letterSpacing: "-0.01em"
  body:
    fontFamily: "Overpass Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.43
  body-data:
    fontFamily: "Overpass Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "13px"
    fontWeight: 600
    lineHeight: 1.4
  caption:
    fontFamily: "Overpass Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "12px"
    fontWeight: 400
    lineHeight: 1.33
  label-caps:
    fontFamily: "Overpass Variable, ui-sans-serif, system-ui, sans-serif"
    fontSize: "11px"
    fontWeight: 800
    lineHeight: 1.45
    letterSpacing: "0.07em"
  identifier:
    fontFamily: "Overpass Mono, ui-monospace, monospace"
    fontSize: "14px"
    fontWeight: 600
    lineHeight: 1.43
rounded:
  sm: "6px"
  md: "8px"
  lg: "10px"
  xl: "12px"
  full: "9999px"
spacing:
  hair: "4px"
  tight: "6px"
  snug: "8px"
  field-gap: "12px"
  cell-x: "16px"
  stack: "20px"
  gutter-tablet: "24px"
  gutter-desktop: "32px"
components:
  button-primary:
    backgroundColor: "{colors.line-orange-deep}"
    textColor: "{colors.enamel-white}"
    rounded: "{rounded.md}"
    typography: "{typography.body-data}"
    padding: "0 16px"
  button-primary-hover:
    backgroundColor: "{colors.line-orange-deep-hover}"
  button-outline:
    backgroundColor: "{colors.enamel-white}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "44px"
    padding: "0 16px"
  button-outline-hover:
    backgroundColor: "{colors.tint-100}"
  input:
    backgroundColor: "{colors.enamel-white}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    typography: "{typography.body}"
    height: "44px"
    padding: "0 12px"
  filter-field:
    backgroundColor: "{colors.tint-50}"
    rounded: "{rounded.xl}"
    padding: "16px"
  filter-chip:
    backgroundColor: "{colors.tint-200}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "4px 8px 4px 12px"
  filter-chip-hover:
    backgroundColor: "{colors.tint-300}"
  table-header:
    backgroundColor: "{colors.tint-100}"
    textColor: "{colors.sign-ink}"
    typography: "{typography.label-caps}"
    rounded: "{rounded.lg}"
    height: "40px"
    padding: "0 16px"
  table-row:
    backgroundColor: "{colors.enamel-white}"
    textColor: "{colors.ink}"
    typography: "{typography.body-data}"
    height: "60px"
    padding: "0 16px"
  table-row-hover:
    backgroundColor: "{colors.tint-50}"
  nav-station:
    backgroundColor: "{colors.enamel-white}"
    textColor: "{colors.sign-ink}"
    rounded: "{rounded.lg}"
    height: "44px"
  nav-station-hover:
    backgroundColor: "{colors.tint-50}"
  nav-station-active:
    backgroundColor: "{colors.tint-100}"
    textColor: "{colors.ink}"
  nav-station-marker-active:
    backgroundColor: "{colors.line-orange}"
    textColor: "{colors.enamel-white}"
    rounded: "{rounded.full}"
    size: "32px"
  count-chip:
    backgroundColor: "{colors.tint-200}"
    textColor: "{colors.tint-800}"
    rounded: "{rounded.sm}"
    padding: "0 6px"
  count-chip-warning:
    backgroundColor: "{colors.warning-surface}"
    textColor: "{colors.warning}"
    rounded: "{rounded.sm}"
    padding: "0 6px"
  brand-bullet-webworks:
    backgroundColor: "{colors.line-orange-deep}"
    textColor: "{colors.enamel-white}"
    rounded: "{rounded.sm}"
    height: "22px"
    width: "28px"
  brand-bullet-satamu:
    backgroundColor: "{colors.enamel-white}"
    textColor: "{colors.satamu-text}"
    rounded: "{rounded.sm}"
    height: "22px"
    width: "28px"
  status-badge-success:
    backgroundColor: "{colors.success-surface}"
    textColor: "{colors.success}"
    rounded: "{rounded.md}"
    padding: "4px 8px"
  status-badge-warning:
    backgroundColor: "{colors.warning-surface}"
    textColor: "{colors.warning}"
    rounded: "{rounded.md}"
    padding: "4px 8px"
  status-badge-danger:
    backgroundColor: "{colors.danger-surface}"
    textColor: "{colors.danger}"
    rounded: "{rounded.md}"
    padding: "4px 8px"
  status-badge-info:
    backgroundColor: "{colors.info-surface}"
    textColor: "{colors.info}"
    rounded: "{rounded.md}"
    padding: "4px 8px"
  status-badge-neutral:
    backgroundColor: "{colors.neutral-surface}"
    textColor: "{colors.neutral}"
    rounded: "{rounded.md}"
    padding: "4px 8px"
---

# Design System: Prasetya Platform

This file is the source for visual decisions in the back office (ADR-0014). `docs/design.md` keeps domain and behaviour rules; once the redesign ships, its visual sections point here. Tokens live as semantic custom properties in `resources/css/app.css`; this file records them, it does not replace them. Derived accessible tokens follow ADR-0003.

Recorded from the shipped pilot: `/transactions`, view Semua, plus the shared shell (sidebar, drawer, header, in-content page header). Other screens inherit the shell and tokens but have not yet been redesigned one by one.

## Overview

**Creative North Star: "Rambu Jalur Commuter"**

A white enamel signboard from a commuter-line station. Orange `#FF5100` is the line colour: it marks where you are and nothing else. Every payment travels one line, Created, Pending, Paid, and each row shows only the stop the gateway's evidence has reached. The sidebar borrows the station marker, not the line: each screen is a ringed station, and the one you are on is filled orange.

The board is calm and legible at laptop distance. Regions are separated by flat tint fields drawn from one white-to-orange ramp, never by cards or shadow. Type is one highway-signage grotesk at three working sizes; rank comes from weight and capital letters, not from size. Status is always a word; colour helps scanning but never carries meaning alone.

Density sits between an admin template and a dashboard: 60 px rows, 44 px controls, a 24 px title. The board refuses the category default of a grey table of pills inside a shadowed card.

**Key Characteristics:**
- One live colour: orange marks the current station, current tab, focus, and unread state.
- One surface ramp: every non-white surface is a step of the tint ramp.
- Flat: no shadow except on overlays.
- Signage type: Overpass, ranked by weight and caps; Overpass Mono for machine identifiers.
- The payment route: a drawn line whose single marker sits at the current stop, with the status word beside it.

## Colors

A white board with one orange line, a nine-step tint ramp for every surface, dark neutral ink for text, and status colours kept to status.

### Primary
- **Line Orange** (`line-orange`, also `--tint-600`, `--primary`, `--ring`, `--line-current`): the Webworks identity orange. Live state only: the filled active nav station, the current-tab underline, the focus ring, the unread dot on the bell, the invoice underline on hover or focus. White on it measures 3.27:1, so it never carries text; the white icon on the active station is non-text (3:1 floor).
- **Deep Line Orange** (`line-orange-deep`, `--primary-strong`, `--tint-700`): the derived fill for anything that carries white text: the primary button and the Webworks brand bullet. White on it 5.0:1; as text on white 5.0:1. Hover deepens to `line-orange-deep-hover`.
- **Accent Text** (`accent-text`, `--primary-text`): orange as words, such as the "Platform" half of the wordmark and the "Reset Filter" link. 5.8:1 on white, 5.6:1 on `tint-50`.

### Secondary
- **Satamu Coral** (`satamu-coral`): Satamu Invitation's own identity colour, used only as the outline of its brand bullet and its brand dot. It is 2.9:1 on white, so it never carries text and never stands alone: the brand name is always printed beside the bullet. **Satamu Text** (`satamu-text`, 6.1:1 on white) carries the "ST" code. `satamu-surface` and `chart-satamu` serve badges and chart series (ADR-0003); the Webworks chart series is `line-orange-deep`.

### Tertiary (status only)
- **Signal Green** (`success`, 5.0:1 on white), **Signal Amber** (`warning`, 5.0:1 on white, 4.5:1 on its surface), **Signal Red** (`danger`, 6.5:1), **Signal Blue** (`info`, 6.7:1, used only on webhook statuses such as Received, Verified, Duplicate), **Signal Grey** (`neutral`, 7.8:1). Each pairs with a `*-surface` tint for badges.

### Neutral
- **Enamel White** (`enamel-white`): page, sidebar, header, table body, inputs.
- **Tint Ramp** (`tint-50` to `tint-800`): the only source of surface colour. `tint-50` filter field and row hover; `tint-100` table header band, active nav row, ghost hover; `tint-200` count and filter chips, avatar, selection; `tint-300` station rings, invoice underline, scrollbar; `tint-400` and `tint-500` the drawn route and its station rings; `tint-800` text on `tint-200` (6.3:1).
- **Ink** (`ink`): body text and titles, 16.6:1 on white.
- **Sign Ink** (`sign-ink`): sidebar labels, table header labels, order status words; 10.5:1 on white, 9.6:1 on `tint-100`.
- **Muted Ink** (`muted-ink`): subtitles, secondary lines, placeholders, field labels; 5.8:1 on white, 5.3:1 on `tint-100`.
- **Hairline** (`hairline`, `--border`): row dividers, header and sidebar edges, tab strip baseline. Decorative only.
- **Input Border** (`input-border`): the same hairline as the sidebar and table rows (`#f0e3d9`), for the stroke of every input, select, textarea, and outline button (owner, 2026-11-14). This is a conscious exception to the 3:1 boundary rule: 1.26:1 on white, recorded in PRODUCT.md. Fields stay findable through their caps label above, placeholder text, and icon; the 2 px `line-orange` focus ring marks the active field.
- **Checkbox Border** (`checkbox-border`): light terracotta `#c47f55` (3.22:1 on white), kept for checkboxes only, because an unchecked box has no label-shaped cue of its own.

### Named Rules
**The One Live Colour Rule.** `line-orange` marks live state and nothing else: active station, current tab, focus, unread. It is never a surface, never a decoration, never text.

**The One Ramp Rule.** Every non-white surface is a step of the tint ramp. A grey or new tint for a surface is a defect.

**The Status Only Rule.** Green, amber, red, and blue appear only on statuses, errors, and warnings, always with a word beside them. In the transaction table only the payment route carries status colour; the order status beside it reads in `sign-ink`, red only when it failed.

**The Derived Token Rule.** Orange or coral used as text, or under white text, uses its derived token (`line-orange-deep`, `accent-text`, `satamu-text`), never the identity value (ADR-0003). Contrast is computed, not guessed.

## Typography

**Body Font:** Overpass Variable (with ui-sans-serif, system-ui)
**Label/Mono Font:** Overpass Mono (500, 600)

**Character:** Overpass descends from highway signage: open, upright, and plain at small sizes. One family does every job; weight and capital letters do the ranking.

### Hierarchy
- **Title** (800, 24px, 1.25, -0.01em): the page title in content, one per page. The only large text in the product.
- **Body** (400, 14px, 1.43): base text, subtitles (in `muted-ink`), inputs, tabs (700), nav labels (600; 800 when active).
- **Body Data** (600 or 700, 13px): table cells, the status word beside the route (700), order status, pagination.
- **Caption** (400, 12px): secondary lines in a row (package and method, "Update …" time, created time), all in `muted-ink`.
- **Label Caps** (800, 11px, 0.07em, uppercase): table header labels, filter field labels, count chips (without caps).
- **Identifier** (Overpass Mono 600, 14px): invoice numbers and other machine identifiers (usernames, AI model ids); the brand line codes WW and ST use it at 11px.

### Named Rules
**The Three Sizes Rule.** Working text sits at 24, 14/13, and 11–12 px. A new level of importance gets a heavier weight or caps, not a bigger size.

**The Machine Voice Rule.** Overpass Mono marks strings a machine issued (invoice, username, model id, line code). Never for prose, labels, or amounts.

## Layout

The shell is a fixed white sidebar at 248 px (76 px collapsed, remembered per browser) from 1024 px up, and a left drawer (288 px, focus-trapped) below that. A 56 px sticky header holds only the sidebar toggle or menu button, the bell, and the account menu; the page title and subtitle live in the content.

Content is centred to a maximum of 1440 px with side gutters of 16 px (phone), 24 px (from 768 px), and 32 px (from 1024 px), 24–28 px from the header, and 20 px between page blocks. Filter fields use 12 px gaps and 12–16 px padding; table cells pad 16 px horizontally.

Tables respond to their own width, not the viewport: with `stackOnMobile`, a table narrower than 60rem becomes one block per row laid out with grid areas (the transaction table: three columns under 60rem, two under 34rem), while the header stays in the markup for screen readers. Tab strips scroll sideways on phones with a fade at the edge. Secondary filters wait behind "Filter Lain"; on phones the three daily selects fold in there too, so rows reach the first screen. Nothing overflows horizontally from 320 px.

Motion is minimal: 150 ms colour transitions, 200 ms ease-out for the sidebar width, all collapsed under `prefers-reduced-motion`.

## Elevation & Depth

The board is flat. Depth comes from tint fields (a `tint-50` filter field, a `tint-100` header band) and hairline dividers. Shadow exists only where something floats above the page.

### Shadow Vocabulary
- **Overlay** (`box-shadow: 0 12px 32px -12px rgb(27 31 36 / 0.25)`, `--shadow-overlay`): dialogs, sheets, menus, popovers, and the focused skip link.

### Named Rules
**The Flat Board Rule.** Cards, tables, inputs, and buttons have no shadow. If a region needs separating, give it a tint step.

## Shapes

Gently rounded rectangles: 6 px for small plates (brand bullets, count chips), 8 px for controls (inputs, selects, buttons, filter chips, status badges), 10 px for the table header band ends and the nav row's right edge, 12 px for the filter field. Stations are full circles. Lines are 3 px with round caps. Borders are 1 px, except the brand bullet's 1.5 px, the nav station ring's 2 px, and the current tab's 3 px underline.

## Components

### Payment Route (signature)
A drawn line of three stations, Created, Pending, Paid (68 × 14 px), with the status word in 13 px bold beside it.
- **Track:** `tint-400`, 3 px, round caps. Stations are white circles with a `tint-500` ring.
- **Current stop:** a "you are here" marker on that station only, on a `tint-100` halo: Created a filled `neutral` dot, Pending a hollow `warning` ring, Paid a filled `success` dot. Stations before it are never drawn as passed: a row carries only the current status, so the route must not imply a history it was not given.
- **Terminus:** Failed and Expired end the line at a `danger` ×; Cancelled at a `neutral` ×. The track becomes dashed (3/4).
- **No payment:** a dashed empty line with "No Payment" in `muted-ink`.
- **Accessibility:** the drawing is `aria-hidden`; the word is the status.

### Navigation (stations)
- **Style:** each item is a station: a 32 px circle with a 2 px `tint-300` ring and a 16 px Heroicon. The stations stand apart; no track joins them (owner, 2026-11-14), so the line belongs to the payment route alone. Rows are 44 px with a 10 px right radius.
- **Hover:** `tint-50` row, ring deepens to `tint-400`, icon to `ink`.
- **Active:** `tint-100` row, station filled `line-orange` with a white icon, label in 800 `ink`, `aria-current="page"`.
- **Counts:** a `tint-200` chip with `tint-800` text, or `warning` on `warning-surface` for issues needing review; collapsed, it rides on the station's corner.
- **Collapsed:** stations only, labels move to `title` and screen-reader text. The footer lists the brand logos for light backgrounds, copied unchanged from the brand sites.

### Tabs
Text tabs on a hairline baseline, 44 px tall, 14 px bold. Current: `ink` with a 3 px `line-orange` underline; others `muted-ink`, hover adds a `tint-300` underline. Counts use the chip above.

### Buttons
- **Shape:** gently rounded (8px).
- **Primary:** `line-orange-deep` fill, white text; hover `line-orange-deep-hover`.
- **Outline:** white with an `input-border` stroke, `ink` text, 44 px tall (Filter Lain, pagination); hover `tint-100`.
- **Ghost:** no fill; hover `tint-100` (header controls, 44 px targets).
- **Focus:** 2 px `line-orange` ring.

### Chips
- **Filter chip:** `tint-200` plate, 12 px bold `ink`, a trailing × icon; hover `tint-300`. Removing one is one press. "Reset Filter" sits beside them as an `accent-text` link.
- **Count chip:** see Navigation.

### Inputs / Fields
- **Style:** white, 1 px `input-border` stroke, 8 px radius, 44 px tall, no shadow. Search carries a 16 px magnifier in `muted-ink`.
- **One theme:** the fill stays white whatever the OS colour scheme. `app.css` declares `color-scheme: light` and gates Tailwind's `dark:` variant behind a `.dark` class that is never set, so shadcn's `dark:bg-input/30` can never tint a field grey.
- **Labels:** Label Caps in `muted-ink` above the field.
- **Focus:** border and a 2 px ring in `line-orange`.
- **Error:** `danger` border.
- **Filter field:** the controls sit together on one `tint-50` field (12 px radius, 12–16 px padding); a `tint-200` rule divides the secondary filters.

### Data Table
- **Header:** a `tint-100` band, 40 px, Label Caps in `sign-ink`, rounded 10 px at its ends; sortable headers are buttons with a direction arrow in `line-orange-deep`.
- **Rows:** 60 px, hairline dividers, hover `tint-50`, first column sticky on horizontal scroll.
- **Anchor:** in the transaction table the invoice number (Identifier type, `tint-300` 2 px underline, `line-orange` on hover or focus) is the row's only link and prefetches its detail; the brand bullet rides under it.
- **Loading:** `tint-100` skeleton bars; refreshed rows dim to 60%.

### Brand Bullet
Like a commuter line's code plate: 22 × 28 px, 6 px radius, the two-letter code in Overpass Mono 11px, the brand name in 13 px bold beside it. The two brands differ by form, not shade, because orange fills mean live state: **Webworks** is a solid `line-orange-deep` plate with white "WW"; **Satamu** is a white plate with a 1.5 px `satamu-coral` outline and "ST" in `satamu-text`.

### Status Badge
Used where a status stands alone (detail pages, other tables): an 8 px plate in the tone's surface with its text colour, 12 px bold English label, and a 14 px Heroicon.

## Do's and Don'ts

### Do:
- **Do** keep `line-orange` (`#FF5100`) for live state: the active station, the current tab, focus, unread.
- **Do** take every surface from the tint ramp and keep ink for text (`ink`, `sign-ink`, `muted-ink`).
- **Do** use the derived tokens for orange or coral under or as text (ADR-0003), and compute contrast for every new pair (AA: 4.5:1 text, 3:1 boundaries).
- **Do** show every status as an English word (Paid, Pending, Failed, …), with colour as support; keep all other copy Indonesian, short labels in Title Case.
- **Do** put the page title (24 px, 800) and subtitle in the content, and keep the header to navigation and account controls.
- **Do** make every input, select, and button 44 px tall with a thin `input-border` stroke and no shadow.
- **Do** draw a payment as the route: one marker at the current stop, a terminus for ended lines, the word beside it.
- **Do** use Heroicons 24 outline at stroke 2 on migrated screens.
- **Do** separate regions with tint fields and hairlines.

### Don't:
- **Don't** rely on the OS dark mode: there is no dark theme, and `dark:` utilities never apply.
- **Don't** use `line-orange` as text, under white text, as a surface, or as decoration (white on it is only 3.27:1).
- **Don't** draw earlier stations of the route as passed; the row knows only the current status.
- **Don't** colour order status in the transaction table except red when it failed; the route carries the colour.
- **Don't** put shadows on cards, tables, inputs, or buttons; shadow is for overlays only.
- **Don't** wrap regions in bordered, shadowed cards.
- **Don't** tell the two brands apart by orange shade; they differ by form (solid plate, outline plate) and always show their names.
- **Don't** recolour or redraw the Webworks or Satamu logos.
- **Don't** add a fourth working type size or an oversized title; rank with weight and caps.
- **Don't** use Overpass Mono for prose, labels, or amounts.
- **Don't** introduce a second icon family on a migrated screen.
