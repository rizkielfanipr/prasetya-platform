import AuditTimeline from '@/components/shared/AuditTimeline.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import ErrorState from '@/components/shared/ErrorState.vue';
import LoadingState from '@/components/shared/LoadingState.vue';
import TablePagination from '@/components/shared/TablePagination.vue';
import type { AuditEntry } from '@/types/backoffice';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('state components', () => {
    it('announces errors and only offers retry when retryable', async () => {
        const plain = mount(ErrorState);
        expect(plain.attributes('role')).toBe('alert');
        expect(plain.find('button').exists()).toBe(false);

        const retryable = mount(ErrorState, { props: { retryable: true } });
        await retryable.find('button').trigger('click');
        expect(retryable.emitted('retry')).toHaveLength(1);
    });

    it('distinguishes empty from loading', () => {
        expect(mount(EmptyState, { props: { title: 'Kosong' } }).text()).toContain('Kosong');

        const loading = mount(LoadingState, { props: { rows: 2 } });
        expect(loading.attributes('role')).toBe('status');
        expect(loading.text()).toBe('Memuat data');
    });
});

describe('TablePagination', () => {
    const meta = { currentPage: 2, lastPage: 3, perPage: 15, total: 40, from: 16, to: 30 };

    it('moves between pages', async () => {
        const wrapper = mount(TablePagination, { props: { meta } });
        const [previous, next] = wrapper.findAll('button');

        await previous?.trigger('click');
        await next?.trigger('click');

        expect(wrapper.emitted('change')).toEqual([[1], [3]]);
        expect(wrapper.text()).toContain('Menampilkan 16–30 dari 40');
    });

    it('disables the ends and hides when empty', () => {
        const first = mount(TablePagination, { props: { meta: { ...meta, currentPage: 1 } } });
        expect(first.findAll('button')[0]?.attributes('disabled')).toBeDefined();

        expect(
            mount(TablePagination, { props: { meta: { ...meta, total: 0 } } })
                .find('nav')
                .exists(),
        ).toBe(false);
    });
});

describe('timelines', () => {
    it('shows audit actor, change, and reason', () => {
        const entry: AuditEntry = {
            id: 1,
            actor: 'Finance Demo',
            action: 'payment.gateway_checked',
            entityType: 'payment',
            entityId: 'x',
            previousState: { status: 'PENDING' },
            newState: { status: 'PAID' },
            reason: 'cek manual',
            requestId: 'r',
            at: '2026-11-01T03:00:00Z',
        };
        const text = mount(AuditTimeline, { props: { entries: [entry] } }).text();

        expect(text).toContain('Finance Demo');
        expect(text).toContain('status: PENDING → status: PAID');
        expect(text).toContain('cek manual');
    });
});
