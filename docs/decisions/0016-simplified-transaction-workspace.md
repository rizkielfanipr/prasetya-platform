# ADR-0016: Simplify the transaction workspace to two views, four review queues, and a single-page detail

- Status: Accepted
- Date: 2026-11-15
- Decision owners: Prasetya Group (owner, 2026-11-15)
- Related: [ADR-0010](0010-consolidated-transaction-workspace.md) (amended), [ADR-0007](0007-duitku-gateway.md), [ADR-0009](0009-single-access-users-username-login.md), [ADR-0014](0014-back-office-redesign.md), [`../runbooks/payment-reconciliation.md`](../runbooks/payment-reconciliation.md)

## Context

ADR-0010 gathered the operational payment screens into one Transaksi workspace with three views (Semua, Perlu Ditinjau, Event Pembayaran) and a transaction detail page with three tabs (Ringkasan, Pembayaran, Aktivitas). With real use by one owner, three problems showed:

- **Perlu Ditinjau lists nine groups, and they overlap.** An open payment past the threshold appears both in *Menunggu terlalu lama* (the payment) and in *Pesanan tanpa pembayaran berhasil* (its order), so one situation is counted twice in the header badge. *Pesanan tanpa percobaan bayar* is a customer who did not check out, which is normal and not a payment problem. *Webhook duplikat* is already marked as handled safely and only for inspection. Three groups (*Status pesanan tidak konsisten*, *Transisi tidak valid*, *Webhook ditolak*) should be zero at all times. A non-zero count means a defect or a wrong gateway key, not daily work.
- **Event Pembayaran is a raw webhook log.** It is useful to diagnose a problem and not useful to check every day. Its operational value, a verified payment for an unknown invoice, is already a Perlu Ditinjau group.
- **The detail page shows the same events three times:** the *Kejadian* list in each payment attempt, the *Riwayat webhook* table, and the *Aktivitas* tab, which already merges order status, payment events, webhooks, notes, and audit. Tabs also hide the open issues behind a count badge on the Pembayaran tab, although an invoice usually has three to ten events.

Large payment operations separate an exception queue (decisions a person must make, usually about money) from the technical event log (where engineers diagnose). A payment detail is usually one page: amount and status on top, then one timeline.

## Decision

1. **The workspace has two views: Semua and Perlu Ditinjau.** `?view=events` redirects to the Log Gateway (point 2) and keeps its filters. An unknown view still falls back to Semua.
2. **Event Pembayaran moves to Pengaturan as Log Gateway.** `GET /settings/gateway-log` shows the same webhook list, filters, and sanitised diagnostics as before, including deliveries for an invoice no order matches. The compatibility route `GET /payment-events` redirects there instead of to the workspace.
3. **Perlu Ditinjau shows four queues.** Each queue is a fixed set of the existing reconciliation groups:

   | Queue (`?queue=`) | Label | Groups |
   |---|---|---|
   | `unmatched` | Uang tanpa pesanan | `unmatched_payments` |
   | `amount_mismatch` | Jumlah tidak cocok | `amount_mismatch` |
   | `waiting` | Menunggu terlalu lama | `orders_without_success` |
   | `system` | Anomali sistem | `paid_inconsistent`, `invalid_transitions`, `rejected_webhooks` |

   The system queue is listed only when it has items. A queue with more than one group shows each group as its own section, with its own pagination. The old `?group=` parameter (and `/reconciliation?group=`) opens the queue that holds the group, or the new home of an informational group (point 4).
4. **Only the six queued groups need action.** `ReconciliationGroup::needsAction()` returns true for those six. The open-issue count in the header, the Perlu Ditinjau tab, and the Dashboard counts only these. The three other groups become informational. Their checks still run, and each still appears on the affected transaction's detail page as information. None of them is counted:
   - `orders_without_attempt`: found in Semua with the new payment filter **No Payment** (`payment_status=NONE`, orders with no payment attempt, the same words as the route column).
   - `pending_too_long`: while its order is still waiting for payment, that order is already in *Menunggu terlalu lama*. An open payment past the threshold always belongs to an order that was created earlier, has an attempt, and has no Paid payment.
   - `duplicate_webhooks`: found in the Log Gateway with the status filter Duplikat.
5. **The transaction detail is one page without tabs.** Top to bottom:
   - Header: invoice, brand, amount, and the payment status drawn as the payment route. The order status moves into the order facts, and a disagreement between the two statuses is reported as an issue (`paid_inconsistent`).
   - Open issues as a notice at the top, each with its Cek status action.
   - The payment attempts, compact.
   - One **Riwayat** timeline: the existing activity timeline.
   - Order facts, customer, and admin notes.

   The per-attempt *Kejadian* list and the *Riwayat webhook* table are removed, because both are already entries of the Riwayat timeline. A webhook's sanitised diagnostics stay one link away in the Log Gateway, filtered to the invoice. An old `?tab=` value is ignored.

### Unchanged

The nine reconciliation queries, the payment and order state machines, `PAID` authority (`ApplyPaymentEvidence` only), append-only evidence, audit, and the gateway status check action. Nothing here repairs a record, and no schema changes.

### Deferred

- Marking an issue as reviewed, with a reason and an audit entry, so a handled item leaves the queue. It needs a stored resolution and gets its own ADR.
- Notifications for *Uang tanpa pesanan* and *Jumlah tidak cocok*.

## Amends ADR-0010

- "Transactions … with `all`, `attention`, and `events` views" becomes `all` and `attention`. The event log lives under Pengaturan, as system activity already does.
- "Unmatched events and issues remain searchable in workspace views" becomes: unmatched verified payments are a Perlu Ditinjau queue, and every webhook, matched or not, is searchable in the Log Gateway.
- The feature-parity requirement for all nine groups now applies to the domain report and to the transaction detail. Three groups are no longer listed in the workspace.

## Alternatives considered

- **Merge the overlapping groups in the domain report.** Rejected: the nine queries are tested evidence of distinct conditions. Grouping them for display keeps every check, and lets the classification change without rewriting the report.
- **Merge `pending_too_long` and `orders_without_success` into one mixed list.** Rejected: one holds payments and the other orders, so a merged list would paginate two models together. The order-level group already names every waiting case once.
- **Keep Event Pembayaran as a third tab but de-emphasised.** Rejected: it keeps a technical log next to daily work, and the owner chose to move it.
- **Keep the detail tabs and only remove the duplicated tables.** Rejected: with so few events per invoice, tabs hide the issue notice and the timeline for no gain.

## Consequences

- The header badge drops, because waiting cases are no longer counted twice and abandoned checkouts are no longer counted. The number now means "decisions waiting for you".
- An abandoned checkout is no longer a prompt to act. Following up a customer is a sales task, found with the No Payment filter.
- Tests that open `?view=events`, select `group-*` cards, or switch detail tabs change. New tests cover the redirects, the queue-to-group mapping, the `NONE` payment filter, and the needs-action classification.
- The payment reconciliation runbook names the new destinations.

## Validation

- Feature tests: each queue returns exactly its groups; `?group=` and `/reconciliation?group=` land on the right queue or filter; `?view=events` and `/payment-events` redirect to the Log Gateway with filters; `openIssueCount` counts only the six groups; `payment_status=NONE` lists orders without an attempt.
- Detail: issues, attempts, timeline, and notes on one page; no webhook table; a Cek status redirect returns to the same page.
- Browser tests for the two-view workspace, queues, Log Gateway, and the detail page at 320, 768, and 1280 px.
