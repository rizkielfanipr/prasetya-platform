---
version: 1
slug: "resources-js-pages-dashboard-dashboardpage-vue"
primary_target: "resources/js/pages/dashboard/DashboardPage.vue"
related_targets: ["resources/js/features/dashboard/RevenuePanel.vue"]
---

# Dashboard (ADR-0014 migration)

Scope: `/dashboard`. Mode: Operate. World: settled (DESIGN.md, Rambu Jalur Commuter); this round chose composition only.

Audience and job: the owner, on a laptop, answering four questions at a glance: how much revenue came in, what needs action, how the two brands compare, and what arrived recently. Data: summary (totalPaid, successful, pending, failedOrExpired, activeOrders, each with previous), brand summaries (always both brands), deferred revenue series, eight recent transactions. Brand and period/date range scope summary and chart; payment status scopes only the recent table.

## Direction contract

THESIS: The page's head is the payment route at page scale: Pending, Paid, and a Failed/Expired terminus, each with its count and a link to the filtered transactions. Refuses the category default: a row of identical icon-tile metric cards.

OWN-WORLD: DESIGN.md unchanged: white board, tint fields, orange for live state only, status colours only on status words, Overpass, brand bullets by form, no shadows.

STORY: The owner reads revenue in at the Paid station, sees what is waiting or stopped, jumps to the filtered list, compares the brands, and scans what arrived.

FIRST VIEWPORT: Title and subtitle; a scope bar (Brand segmented, Periode select, custom date range, comparison note) that visibly owns the whole page; a tint route band with Pending, Paid (largest: revenue amount and Paid count with change), and the Failed/Expired terminus, plus a Perlu Ditinjau chip and active orders; below, the revenue chart at 2/3 beside the two-brand comparison at 1/3; then Transaksi Terbaru with route strips.

FORM: Rute sebagai Tulang Punggung, dealt lead of a surface round (my position 7 of 7); seed:e88660460d5492bd. Signature move: the page-scale payment route.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance
