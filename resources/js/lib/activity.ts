import type { StatusTone } from '@/lib/status';
import { auditActionLabels, eventTypeLabels, orderStatuses, paymentStatuses, rejectionLabels, sourceLabels, webhookStatuses } from '@/lib/status';
import type { ActivitySource, TransactionActivityEntry } from '@/types/backoffice';

export interface ActivityDescription {
    title: string;
    detail: string | null;
    tone: StatusTone;
}

export const activitySourceLabels: Record<ActivitySource, string> = {
    order_status: 'Status pesanan',
    payment_event: 'Pembayaran',
    webhook: 'Webhook',
    note: 'Catatan',
    audit: 'Audit',
};

const text = (value: unknown): string | null => (typeof value === 'string' && value !== '' ? value : null);
const join = (...parts: (string | null)[]): string | null => parts.filter((part): part is string => part !== null).join(' · ') || null;
/** A map entry only when the key really exists: server data may carry a value this client does not know yet. */
function lookup<T>(map: Record<string, T>, key: unknown): T | undefined {
    return typeof key === 'string' && Object.hasOwn(map, key) ? map[key] : undefined;
}

const orderLabel = (status: unknown) => (text(status) ? (lookup(orderStatuses, status)?.label ?? String(status)) : '—');
const paymentLabel = (status: unknown) => (text(status) ? (lookup(paymentStatuses, status)?.label ?? String(status)) : '—');

/** Words for one timeline entry. Only the sanitised fields the server sends are read. */
export function describeActivity(entry: TransactionActivityEntry): ActivityDescription {
    const d = entry.data;

    switch (entry.source) {
        case 'order_status':
            return {
                title: `Pesanan: ${orderLabel(d.from)} → ${orderLabel(d.to)}`,
                detail: join(sourceLabels[String(d.via)] ?? text(d.via), text(d.reason) ? `Alasan: ${d.reason}` : null),
                tone: d.to === 'CONFIRMED' ? 'success' : d.to === 'CANCELLED' || d.to === 'EXPIRED' ? 'neutral' : 'info',
            };
        case 'payment_event': {
            const base = eventTypeLabels[String(d.type)] ?? String(d.type);
            const title = d.type === 'STATUS_CHANGED' ? `${base}: ${paymentLabel(d.from)} → ${paymentLabel(d.to)}` : base;

            return {
                title: `Percobaan ${d.attempt}: ${title}`,
                detail: join(
                    sourceLabels[String(d.via)] ?? text(d.via),
                    text(d.gatewayStatus) ? `Status gateway: ${d.gatewayStatus}` : null,
                    text(d.failureCode) ? `Kode: ${d.failureCode}` : null,
                ),
                tone: d.type === 'EVIDENCE_REJECTED' || d.type === 'CHECKOUT_FAILED' ? 'danger' : d.to === 'PAID' ? 'success' : 'info',
            };
        }
        case 'webhook': {
            const status = lookup(webhookStatuses, d.status);

            return {
                title: `Webhook ${status?.label.toLowerCase() ?? String(d.status)}`,
                detail: join(
                    text(d.invoice),
                    text(d.rejection) ? (rejectionLabels[String(d.rejection)] ?? String(d.rejection)) : null,
                    d.signatureValid === false ? 'Tanda tangan tidak valid' : null,
                    text(d.channel) ? `Kanal: ${d.channel}` : null,
                ),
                tone: status?.tone ?? 'neutral',
            };
        }
        case 'note':
            return { title: 'Catatan admin', detail: text(d.body), tone: 'neutral' };
        case 'audit':
            return {
                title: auditActionLabels[String(d.action)] ?? String(d.action),
                detail: text(d.reason) ? `Alasan: ${d.reason}` : null,
                tone: 'neutral',
            };
    }
}
