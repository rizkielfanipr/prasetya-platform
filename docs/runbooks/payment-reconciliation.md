# Payment reconciliation

Reconciliation only shows problems. Nothing repairs records automatically, and no screen can mark a payment paid. The one action is **Cek status**: it asks DOKU, applies the answer through the same checks as a notification, and writes an audit entry. Finance and Super Admin can run it.

DOKU advises checking at least 60 seconds after payment. Find the transaction in DOKU Back Office under Report, Checkout Orders, using the invoice number, including any `-2` suffix.

| Group | Means | Do |
|---|---|---|
| Pesanan tanpa percobaan bayar | Customer did not reach checkout, or it failed before an attempt | Usually nothing. It expires by itself. Many at once may mean a site integration problem |
| Pesanan tanpa pembayaran berhasil | Attempts exist, no success, past the threshold | Run Cek status. If DOKU shows paid, a notification was lost |
| Pembayaran tanpa pesanan | Verified notification for an unknown invoice | Check DOKU for the invoice. Look for a wrong merchant or environment. Money may have moved: **escalate to Finance** |
| Jumlah tidak cocok | Verified notification with a different amount. Payment not applied | **Do not fulfil.** Compare in DOKU. Escalate; a code or catalogue fix may be needed |
| Webhook ditolak | Failed verification | One-off: inspect. A burst: see the incident runbook, it may be an attack or a wrong secret |
| Webhook duplikat | Repeat delivery, handled safely | Nothing. For inspection only and not counted in the bell |
| Menunggu terlalu lama | Attempt still open past the threshold | Run Cek status. The scheduler expires it after its due time and a grace period |
| Status pesanan tidak konsisten | Paid but not confirmed, confirmed without payment, or paid more than once | Paid on a cancelled order or paid twice means a **possible refund**: handle in DOKU and note it. Confirmed without payment should not happen: treat as an incident |
| Transisi tidak valid | Evidence asked for a disallowed or unsupported change, including `REFUNDED` | For a refund: refunds are handled outside this release, so add a note. Otherwise investigate |

## Lost notification

1. Confirm in DOKU that it is paid.
2. Either resend from DOKU Back Office (Integration, HTTP Notification), or use Cek status.
3. Confirm the transaction now shows Berhasil and Terkonfirmasi, with an audit entry.

## When a correction is not possible through the app

Direct database edits bypass the state machine and audit log, so they are a last resort. Get a second person's review, take a backup, record the SQL and reason as an admin note, and raise an issue so the case becomes a proper action.

## Thresholds

`RECONCILIATION_PENDING_MINUTES` (120) and `RECONCILIATION_NO_ATTEMPT_MINUTES` (30). The bell in the header counts all groups except duplicates and is cached for 60 seconds.
