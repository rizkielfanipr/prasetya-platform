import AttentionView from '@/features/reconciliation/AttentionView.vue';
import WebhookEventTable from '@/features/payments/WebhookEventTable.vue';
import { workspaceViews } from '@/lib/workspace';
import type { ReconciliationGroup, WebhookEventRow } from '@/types/backoffice';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

const groups: ReconciliationGroup[] = [
    'orders_without_attempt',
    'orders_without_success',
    'unmatched_payments',
    'amount_mismatch',
    'rejected_webhooks',
    'duplicate_webhooks',
    'pending_too_long',
    'paid_inconsistent',
    'invalid_transitions',
];
const counts = Object.fromEntries(groups.map((group) => [group, 0])) as Record<ReconciliationGroup, number>;

function mountAttention(overrides: Partial<Record<ReconciliationGroup, number>> = {}, group: ReconciliationGroup = 'orders_without_attempt') {
    return mount(AttentionView, {
        props: {
            group,
            counts: { ...counts, ...overrides },
            needsAction: groups.filter((key) => key !== 'duplicate_webhooks'),
            thresholds: { pending_too_long_minutes: 120, no_attempt_after_minutes: 30 },
            issues: { data: [], meta: { currentPage: 1, lastPage: 1, perPage: 15, total: 0, from: null, to: null } },
            path: '/transactions',
            baseQuery: { view: 'attention' },
        },
    });
}

describe('workspace views', () => {
    it('lists Semua, Perlu Ditinjau, and Event Pembayaran in that order', () => {
        expect(workspaceViews.map((view) => view.value)).toEqual(['all', 'attention', 'events']);
        expect(workspaceViews.map((view) => view.label)).toEqual(['Semua', 'Perlu Ditinjau', 'Event Pembayaran']);
    });
});

describe('AttentionView', () => {
    it('links every one of the nine groups inside the workspace, marking the current one', () => {
        const wrapper = mountAttention({}, 'pending_too_long');
        const links = wrapper.findAll('nav a');

        expect(links).toHaveLength(9);
        expect(links.map((link) => link.attributes('href'))).toContain('/transactions?view=attention&group=unmatched_payments');
        expect(wrapper.get('[data-testid="group-pending_too_long"]').attributes('aria-current')).toBe('true');
    });

    it('totals only the groups that need action, and labels duplicates as information', () => {
        const wrapper = mountAttention({ orders_without_attempt: 2, amount_mismatch: 1, duplicate_webhooks: 5 });

        expect(wrapper.get('[data-testid="attention-total"]').text()).toContain('3 isu perlu tindakan');
        expect(wrapper.get('[data-testid="group-duplicate_webhooks"]').text()).toContain('Informasi, tidak dihitung');
    });

    it('explains the threshold of time-based groups', () => {
        expect(mountAttention({}, 'pending_too_long').text()).toContain('Ambang: 120 menit.');
        expect(mountAttention({}, 'orders_without_attempt').text()).toContain('Ambang: 30 menit setelah pesanan dibuat.');
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
