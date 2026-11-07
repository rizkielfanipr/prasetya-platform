import TransactionActivityTimeline from '@/features/transactions/TransactionActivityTimeline.vue';
import TransactionIssuesPanel from '@/features/transactions/TransactionIssuesPanel.vue';
import { describeActivity } from '@/lib/activity';
import { transactionDetailTabs } from '@/lib/transaction-tabs';
import type { TransactionActivityEntry, TransactionIssue } from '@/types/backoffice';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

const entry = (source: TransactionActivityEntry['source'], data: TransactionActivityEntry['data'], id = 1): TransactionActivityEntry => ({
    id: `${source}-${id}`,
    source,
    sourceId: id,
    at: '2026-11-07T03:00:00.000000Z',
    actor: source === 'note' || source === 'audit' ? 'Admin Demo' : null,
    data,
});

describe('describeActivity', () => {
    it('words each source from its sanitised fields', () => {
        expect(describeActivity(entry('order_status', { from: 'PENDING_PAYMENT', to: 'CONFIRMED', via: 'GATEWAY_NOTIFICATION', reason: null }))).toEqual({
            title: 'Pesanan: Menunggu bayar → Terkonfirmasi',
            detail: 'Notifikasi gateway',
            tone: 'success',
        });
        expect(
            describeActivity(
                entry('payment_event', {
                    attempt: 2,
                    type: 'STATUS_CHANGED',
                    from: 'PENDING',
                    to: 'PAID',
                    via: 'GATEWAY_CHECK',
                    gatewayStatus: 'SUCCESS',
                    failureCode: null,
                }),
            ).title,
        ).toBe('Percobaan 2: Status berubah: Menunggu → Berhasil');
        expect(
            describeActivity(
                entry('webhook', {
                    invoice: 'PW-1',
                    status: 'REJECTED',
                    rejection: 'AMOUNT_MISMATCH',
                    signatureValid: true,
                    isDuplicate: false,
                    channel: 'SP',
                }),
            ),
        ).toMatchObject({
            title: 'Webhook ditolak',
            detail: 'PW-1 · Jumlah tidak cocok · Kanal: SP',
            tone: 'danger',
        });
        expect(describeActivity(entry('note', { body: 'Pelanggan menelepon' }))).toMatchObject({ title: 'Catatan admin', detail: 'Pelanggan menelepon' });
        expect(describeActivity(entry('audit', { action: 'payment.gateway_checked', entityType: 'payment', reason: 'cek manual' }))).toMatchObject({
            title: 'Status diperiksa ke gateway',
            detail: 'Alasan: cek manual',
        });
    });

    it('falls back to the code for values this client does not know', () => {
        expect(describeActivity(entry('audit', { action: 'future.action', entityType: 'order', reason: null })).title).toBe('future.action');
        expect(
            describeActivity(
                entry('webhook', { invoice: null, status: 'NEW_STATUS', rejection: null, signatureValid: null, isDuplicate: false, channel: null }),
            ).title,
        ).toBe('Webhook NEW_STATUS');
    });
});

describe('transaction detail tabs', () => {
    it('reads the tab from the URL and falls back to the summary', () => {
        expect(transactionDetailTabs.fromUrl('/transactions/01abc?tab=payments')).toBe('payments');
        expect(transactionDetailTabs.fromUrl('/transactions/01abc?tab=activity')).toBe('activity');
        expect(transactionDetailTabs.fromUrl('/transactions/01abc?tab=../../etc')).toBe('summary');
        expect(transactionDetailTabs.fromUrl('/transactions/01abc')).toBe('summary');
    });
});

describe('TransactionActivityTimeline', () => {
    it('keeps the server order and writes the source next to each entry', () => {
        const wrapper = mount(TransactionActivityTimeline, {
            props: { entries: [entry('note', { body: 'Kedua' }, 2), entry('audit', { action: 'order.note_added', entityType: 'order', reason: null }, 1)] },
        });

        const items = wrapper.findAll('li');
        const texts = items.map((item) => item.text());
        expect(items.map((item) => item.attributes('data-source'))).toEqual(['note', 'audit']);
        expect(texts[0]).toContain('Catatan');
        expect(texts[0]).toContain('Admin Demo');
        expect(texts[1]).toContain('Catatan admin ditambahkan');
    });

    it('shows an empty state without entries', () => {
        expect(mount(TransactionActivityTimeline, { props: { entries: [] } }).text()).toContain('Belum ada aktivitas');
    });
});

describe('TransactionIssuesPanel', () => {
    const issue = (group: TransactionIssue['group'], needsAction: boolean, checkablePaymentId: string | null = null): TransactionIssue => ({
        key: `${group}-1`,
        group,
        needsAction,
        invoice: 'PW-202611-000001',
        brand: 'WEBWORKS',
        amount: 1000,
        orderStatus: 'PENDING_PAYMENT',
        paymentStatus: 'PENDING',
        detail: null,
        orderId: '01abc',
        checkablePaymentId,
        at: '2026-11-07T03:00:00Z',
    });

    it('separates issues needing action from information, in words', () => {
        const wrapper = mount(TransactionIssuesPanel, {
            props: { issues: [issue('pending_too_long', true), issue('duplicate_webhooks', false)] },
            global: { stubs: { GatewayCheckDialog: true } },
        });

        const texts = wrapper.findAll('li').map((item) => item.text());
        expect(texts[0]).toContain('Perlu tindakan');
        expect(texts[1]).toContain('Informasi');
    });

    it('offers Cek status only where the issue has a checkable payment', () => {
        const wrapper = mount(TransactionIssuesPanel, {
            props: { issues: [issue('pending_too_long', true, '01pay'), issue('orders_without_attempt', true)] },
            global: { stubs: { GatewayCheckDialog: { template: '<button class="check" />', props: ['paymentId', 'invoice'] } } },
        });

        expect(wrapper.findAll('.check')).toHaveLength(1);
    });

    it('renders nothing without issues', () => {
        expect(mount(TransactionIssuesPanel, { props: { issues: [] } }).html()).not.toContain('Perlu ditinjau');
    });
});
