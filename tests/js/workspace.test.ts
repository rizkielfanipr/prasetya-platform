import AttentionView from '@/features/reconciliation/AttentionView.vue';
import WebhookEventTable from '@/features/payments/WebhookEventTable.vue';
import { workspaceViews } from '@/lib/workspace';
import type { AttentionQueue, AttentionSection, ReconciliationGroup, WebhookEventRow } from '@/types/backoffice';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

const empty = { data: [], meta: { currentPage: 1, lastPage: 1, perPage: 15, total: 0, from: null, to: null } };
const section = (group: ReconciliationGroup, pageName = 'page'): AttentionSection => ({ group, pageName, issues: empty });

function mountAttention(
    counts: Partial<Record<AttentionQueue, number>> = {},
    queue: AttentionQueue = 'unmatched',
    sections: AttentionSection[] = [section('unmatched_payments')],
    queues: AttentionQueue[] = ['unmatched', 'amount_mismatch', 'waiting'],
) {
    return mount(AttentionView, {
        props: {
            queue,
            queues,
            counts: { unmatched: 0, amount_mismatch: 0, waiting: 0, system: 0, ...counts },
            thresholds: { pending_too_long_minutes: 120, no_attempt_after_minutes: 30 },
            sections,
            path: '/transactions',
            baseQuery: { view: 'attention' },
        },
    });
}

describe('workspace views', () => {
    it('lists Semua and Perlu Ditinjau; the webhook log lives in Pengaturan (ADR-0016)', () => {
        expect(workspaceViews.map((view) => view.value)).toEqual(['all', 'attention']);
        expect(workspaceViews.map((view) => view.label)).toEqual(['Semua', 'Perlu Ditinjau']);
    });
});

describe('AttentionView', () => {
    it('links the queues the server lists, marking the current one', () => {
        const wrapper = mountAttention({}, 'waiting', [section('orders_without_success')]);
        const links = wrapper.findAll('nav a');

        expect(links).toHaveLength(3);
        expect(links.map((link) => link.attributes('href'))).toContain('/transactions?view=attention&queue=amount_mismatch');
        expect(wrapper.get('[data-testid="queue-waiting"]').attributes('aria-current')).toBe('true');
    });

    it('totals every queue, and points to where the uncounted groups live', () => {
        const wrapper = mountAttention({ unmatched: 1, amount_mismatch: 1, waiting: 2 });

        expect(wrapper.get('[data-testid="attention-total"]').text()).toContain('4 isu menunggu keputusan');
        expect(wrapper.find('a[href="/transactions?payment_status=NONE"]').exists()).toBe(true);
        expect(wrapper.find('a[href="/settings/gateway-log?status=DUPLICATE"]').exists()).toBe(true);
    });

    it('explains the threshold of the waiting queue', () => {
        expect(mountAttention({}, 'waiting', [section('orders_without_success')]).text()).toContain('Ambang: 120 menit sejak pesanan dibuat.');
        expect(mountAttention().text()).not.toContain('Ambang');
    });

    it('shows each group of the system queue as its own section', () => {
        const wrapper = mountAttention(
            { system: 2 },
            'system',
            [section('paid_inconsistent', 'page_paid_inconsistent'), section('rejected_webhooks', 'page_rejected_webhooks')],
            ['unmatched', 'amount_mismatch', 'waiting', 'system'],
        );

        expect(wrapper.find('[data-testid="section-paid_inconsistent"]').text()).toContain('Status pesanan tidak konsisten');
        expect(wrapper.find('[data-testid="section-rejected_webhooks"]').text()).toContain('Webhook ditolak');
    });
});

describe('WebhookEventTable', () => {
    const row = (orderId: string | null): WebhookEventRow => ({
        id: '01webhookevent000000000001',
        provider: 'DUITKU',
        invoice: 'PW-209901-000001',
        orderId,
        eventType: '00',
        channel: 'SP',
        amount: 1000,
        signatureValid: true,
        status: 'REJECTED',
        isDuplicate: false,
        rejectionReason: 'UNKNOWN_INVOICE',
        requestId: null,
        receivedAt: '2026-11-07T03:00:00Z',
        processedAt: null,
        diagnostics: null,
    });

    it('links evidence to its transaction, and says so when there is none', () => {
        const linked = mount(WebhookEventTable, { props: { rows: [row('01order')] } });
        const orphan = mount(WebhookEventTable, { props: { rows: [row(null)] } });

        expect(linked.find('a[href="/transactions/01order"]').exists()).toBe(true);
        expect(orphan.find('a').exists()).toBe(false);
        expect(orphan.get('[data-testid="no-transaction"]').text()).toBe('Transaksi tidak ditemukan');
    });
});
