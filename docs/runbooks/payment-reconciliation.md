# Payment reconciliation

Reconciliation only shows problems. Nothing repairs records automatically, and no screen can mark a payment paid. The one action is **Cek status**: it asks Duitku, applies the answer through the same checks as a notification, and writes an audit entry. Every signed-in user can run it ([ADR-0009](../decisions/0009-single-access-users-username-login.md)).

Where to work ([ADR-0010](../decisions/0010-consolidated-transaction-workspace.md), [ADR-0016](../decisions/0016-simplified-transaction-workspace.md)):

- **Transaksi → Perlu Ditinjau** (`/transactions?view=attention&queue=<queue>`) holds four queues: *Uang tanpa pesanan*, *Jumlah tidak cocok*, *Menunggu terlalu lama*, and *Anomali sistem* (shown only when it has items). The table below says which groups each holds. Bookmarks to `/reconciliation?group=…` redirect to the right queue.
- **Pengaturan → Log Gateway** (`/settings/gateway-log`) finds any webhook delivery by invoice, status, or date, including deliveries for an invoice no order matches (*Transaksi tidak ditemukan*), with sanitised diagnostics.
- **The transaction detail** is one page: the open issues at the top, each payment attempt with **Cek status** and the last check, and one **Riwayat** of status history, payment events, webhooks, notes, and audit.

Find the transaction in the report menu of the Duitku dashboard, using the invoice number, including any `-2` suffix. Duitku warns against automatic repeated status checks, since its limit can block requests for about an hour, so run Cek status only when needed.

| Queue | Group | Means | Do |
|---|---|---|---|
| Uang tanpa pesanan | Pembayaran tanpa pesanan | Verified notification for an unknown invoice | Check Duitku for the invoice. Look for a wrong merchant or environment. Money may have moved: **escalate to Finance** |
| Jumlah tidak cocok | Jumlah tidak cocok | Verified notification with a different amount. Payment not applied | **Do not fulfil.** Compare in Duitku. Escalate; a code or catalogue fix may be needed |
| Menunggu terlalu lama | Pesanan tanpa pembayaran berhasil | Attempts exist, no success, past the threshold | Run Cek status. If Duitku shows paid, a callback was lost. The scheduler expires an open attempt after its due time and a grace period |
| Anomali sistem | Status pesanan tidak konsisten | Paid but not confirmed, confirmed without payment, or paid more than once | Paid on a cancelled order or paid twice means a **possible refund**: Duitku has no refund service, so transfer manually and note it. Confirmed without payment should not happen: treat as an incident |
| Anomali sistem | Transisi tidak valid | Evidence asked for a disallowed or unsupported change | Investigate |
| Anomali sistem | Webhook ditolak | Failed verification | One-off: inspect. A burst: see the incident runbook, it may be an attack or a wrong secret |
| (information) | Pesanan tanpa percobaan bayar | Customer did not reach checkout, or it failed before an attempt | Usually nothing; it expires by itself. Find them in Semua with the payment filter **No Payment**. Many at once may mean a site integration problem |
| (information) | Menunggu terlalu lama (payment) | Attempt still open past the threshold | Already counted through its order in *Menunggu terlalu lama*; shown on the transaction detail as information |
| (information) | Webhook duplikat | Repeat delivery, handled safely | Nothing. In the Log Gateway with status Duplicate |

## Satamu orders

- Invoices start with `SAT-`; filter by brand Satamu, package, theme, event type, or event date on Transaksi.
- Fulfilment is manual in the first release. After `Berhasil` and `Terkonfirmasi`, contact the customer on WhatsApp for the invitation material and record progress as an admin note. Never start work on `Jumlah tidak cocok` or an unconfirmed order.
- Deactivating a Satamu package stops new orders only. Attempts already issued still receive callbacks and appear here as usual; reconcile them as for Webworks.
- The customer's status page reads the platform. If a customer says they paid but the page says pending, follow **Lost notification**; never confirm from a screenshot of the Duitku page.

## Lost notification

1. Confirm in Duitku that it is paid.
2. Either resend from the report menu of the Duitku dashboard, or use Cek status.
3. Confirm the transaction now shows Berhasil and Terkonfirmasi, and that its **Riwayat** has the `Status diperiksa ke gateway` entry.

## When a correction is not possible through the app

Direct database edits bypass the state machine and audit log, so they are a last resort. Get a second person's review, take a backup, record the SQL and reason as an admin note, and raise an issue so the case becomes a proper action.

## Thresholds

`RECONCILIATION_PENDING_MINUTES` (120) and `RECONCILIATION_NO_ATTEMPT_MINUTES` (30). The bell in the header counts the six queued groups only (ADR-0016) and is cached for 60 seconds.
