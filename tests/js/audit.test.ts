import AuditTimeline from '@/components/shared/AuditTimeline.vue';
import type { AuditEntry } from '@/types/backoffice';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

const entry = (action: string, overrides: Partial<AuditEntry> = {}): AuditEntry => ({
    id: 1,
    actor: 'Admin Demo',
    action,
    entityType: 'user',
    entityId: '01user',
    previousState: null,
    newState: { username: 'sari' },
    reason: null,
    requestId: null,
    at: '2026-11-07T03:00:00Z',
    ...overrides,
});

describe('AuditTimeline', () => {
    it('words known actions and keeps unknown codes readable', () => {
        const wrapper = mount(AuditTimeline, { props: { entries: [entry('user.password_reset'), entry('future.action', { id: 2 })] } });
        const items = wrapper.findAll('li').map((item) => item.text());

        expect(items[0]).toContain('Kata sandi diatur oleh admin');
        expect(items[1]).toContain('future.action');
    });

    it('shows the reason and the actor', () => {
        const text = mount(AuditTimeline, { props: { entries: [entry('payment.gateway_checked', { reason: 'cek manual' })] } }).text();

        expect(text).toContain('Status diperiksa ke gateway');
        expect(text).toContain('Alasan: cek manual');
        expect(text).toContain('Admin Demo');
    });
});
