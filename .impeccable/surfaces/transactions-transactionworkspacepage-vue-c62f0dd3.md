---
version: 1
slug: "transactions-transactionworkspacepage-vue-c62f0dd3"
primary_target: "resources/js/pages/transactions/TransactionWorkspacePage.vue"
related_targets: ["resources/js/components/layout/AppLayout.vue","resources/js/features/transactions/views/AllTransactionsView.vue"]
---

# Transaksi (pilot redesign, ADR-0014)

Scope: `/transactions`, view Semua, plus the app shell (sidebar, mobile drawer, header, in-content page header). Mode: Operate.

Audience and job: the owner, on a laptop, checking which payments came in, are waiting, or failed, and finding one transaction by invoice or name when a customer asks. No live orders yet; demo data only. Must not feel like a generic admin template, too dense, too airy, or too colourful.

Constraints: PRODUCT.md and ADR-0014 bind. Routes, props, filters, and data contracts unchanged. Status labels in English, always in words.

## Direction contract

THESIS: Every payment travels one line, Created → Pending → Paid, and the row shows the stop it has reached, only as far as gateway evidence carried it. Refuses the category default: grey table of pills inside a shadowed card.

OWN-WORLD: White enamel signboard. Orange `#FF5100` is the line colour and the only live-state colour (active nav station, current stop, primary action, focus); a nine-step white-to-orange tint ramp is the only source of surface colour. Overpass (highway-signage grotesk) at three sizes, rank by weight and caps. Brand bullets: rounded-square line badges WW / ST. Regions divided by flat tint fields, no cards, no shadow outside overlays.

STORY: The owner sees at a glance which payments reached Paid, which stopped at Pending, and which ended at a terminus, finds a row by invoice, and opens it.

FIRST VIEWPORT: White sidebar 248 px, nav items as stations on one vertical tint line, active station filled orange; 56 px header with collapse toggle, bell, account. Content: 24 px title Transaksi plus subtitle, tabs Semua / Perlu Ditinjau / Event Pembayaran, a tint filter field (search, Brand, Pembayaran, Pesanan, Filter Lain), then the table with tint header band, invoice as the row anchor, Pembayaran as a route strip with the stop named in words.

FORM: Rambu Jalur Commuter (KRL wayfinding), position 3 on the ordered list; seed:c530bedfc76fa504. Signature move: the payment route strip in each row, echoed by the station-line sidebar nav. Raises: orange only for live state; rank by weight not size; flat tint fields not cards; invoice as anchor; Inertia prefetch on row hover/focus; one tint ramp.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Unresolved

- Lucide stays on unmigrated screens until each moves; Heroicons in the shell and this screen.
