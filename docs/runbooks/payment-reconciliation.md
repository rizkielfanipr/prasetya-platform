# Payment reconciliation

Reconciliation only shows problems. Nothing repairs records automatically, and no screen can mark a payment paid. The one action is **Cek status**: it asks Duitku, applies the answer through the same checks as a notification, and writes an audit entry. Every signed-in user can run it ([ADR-0009](../decisions/0009-single-access-users-username-login.md)).

Where to work ([ADR-0010](../decisions/0010-consolidated-transaction-workspace.md)):

- **Transaksi → Perlu Ditinjau** (`/transactions?view=attention&group=<group>`) lists the groups below. Bookmarks to `/reconciliation` redirect here during the compatibility release.
- **Transaksi → Event Pembayaran** (`/transactions?view=events`) finds any webhook delivery by invoice, status, or date, including deliveries for an invoice no order matches (*Transaksi tidak ditemukan*).
- **The transaction detail → Pembayaran** shows that order's open issues and each payment attempt with **Cek status** and the last check; **→ Aktivitas** shows its status history, payment events, webhooks, notes, and audit in one timeline.

Find the transaction in the report menu of the Duitku dashboard, using the invoice number, including any `-2` suffix. Duitku warns against automatic repeated status checks, since its limit can block requests for about an hour, so run Cek status only when needed.

| Group | Means | Do |
|---|---|---|
| Pesanan tanpa percobaan bayar | Customer did not reach checkout, or it failed before an attempt | Usually nothing. It expires by itself. Many at once may mean a site integration problem |
| Pesanan tanpa pembayaran berhasil | Attempts exist, no success, past the threshold | Run Cek status. If Duitku shows paid, a callback was lost |
| Pembayaran tanpa pesanan | Verified notification for an unknown invoice | Check Duitku for the invoice. Look for a wrong merchant or environment. Money may have moved: **escalate to Finance** |
| Jumlah tidak cocok | Verified notification with a different amount. Payment not applied | **Do not fulfil.** Compare in Duitku. Escalate; a code or catalogue fix may be needed |
| Webhook ditolak | Failed verification | One-off: inspect. A burst: see the incident runbook, it may be an attack or a wrong secret |
| Webhook duplikat | Repeat delivery, handled safely | Nothing. For inspection only and not counted in the bell |
| Menunggu terlalu lama | Attempt still open past the threshold | Run Cek status. The scheduler expires it after its due time and a grace period |
| Status pesanan tidak konsisten | Paid but not confirmed, confirmed without payment, or paid more than once | Paid on a cancelled order or paid twice means a **possible refund**: Duitku has no refund service, so transfer manually and note it. Confirmed without payment should not happen: treat as an incident |
| Transisi tidak valid | Evidence asked for a disallowed or unsupported change | Investigate |

## Satamu orders

- Invoices start with `SAT-`; filter by brand Satamu, package, theme, event type, or event date on Transaksi.
- Fulfilment is manual in the first release. After `Berhasil` and `Terkonfirmasi`, contact the customer on WhatsApp for the invitation material and record progress as an admin note. Never start work on `Jumlah tidak cocok` or an unconfirmed order.
- Deactivating a Satamu package stops new orders only. Attempts already issued still receive callbacks and appear here as usual; reconcile them as for Webworks.
- The customer's status page reads the platform. If a customer says they paid but the page says pending, follow **Lost notification**; never confirm from a screenshot of the Duitku page.

## Lost notification

1. Confirm in Duitku that it is paid.
2. Either resend from the report menu of the Duitku dashboard, or use Cek status.
3. Confirm the transaction now shows Berhasil and Terkonfirmasi, and that its **Aktivitas** tab has the `Status diperiksa ke gateway` entry.

## When a correction is not possible through the app

Direct database edits bypass the state machine and audit log, so they are a last resort. Get a second person's review, take a backup, record the SQL and reason as an admin note, and raise an issue so the case becomes a proper action.

## Thresholds

`RECONCILIATION_PENDING_MINUTES` (120) and `RECONCILIATION_NO_ATTEMPT_MINUTES` (30). The bell in the header counts all groups except duplicates and is cached for 60 seconds.
