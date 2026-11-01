import BrandBadge from '@/components/shared/BrandBadge.vue';
import OrderStatusBadge from '@/components/shared/OrderStatusBadge.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { orderStatuses, paymentStatuses } from '@/lib/status';
import type { OrderStatus, PaymentStatus } from '@/types/backoffice';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('status badges', () => {
    it.each(Object.keys(paymentStatuses) as PaymentStatus[])('payment %s shows text, not colour alone', (status) => {
        const wrapper = mount(PaymentStatusBadge, { props: { status } });

        expect(wrapper.text()).toBe(paymentStatuses[status].label);
        expect(wrapper.find('svg').attributes('aria-hidden')).toBe('true');
    });

    it.each(Object.keys(orderStatuses) as OrderStatus[])('order %s shows its label', (status) => {
        expect(mount(OrderStatusBadge, { props: { status } }).text()).toBe(orderStatuses[status].label);
    });

    it('uses a distinct tone per meaning', () => {
        expect(mount(PaymentStatusBadge, { props: { status: 'PAID' } }).classes()).toContain('text-success');
        expect(mount(PaymentStatusBadge, { props: { status: 'PENDING' } }).classes()).toContain('text-warning');
        expect(mount(PaymentStatusBadge, { props: { status: 'EXPIRED' } }).classes()).toContain('text-danger');
    });

    it('states when no payment exists yet', () => {
        expect(mount(PaymentStatusBadge, { props: { status: null } }).text()).toBe('Belum dibayar');
    });

    it('labels brands with semantic tokens', () => {
        const wrapper = mount(BrandBadge, { props: { brand: 'SATAMU' } });

        expect(wrapper.text()).toBe('Satamu');
        expect(wrapper.classes()).toContain('text-satamu-text');
    });
});
