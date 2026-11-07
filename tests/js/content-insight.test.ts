import ContentInsightPanel from '@/features/content/ContentInsightPanel.vue';
import { formatRate } from '@/lib/content';
import type { InsightSnapshot } from '@/types/content';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

const snapshot = (overrides: Partial<InsightSnapshot> = {}): InsightSnapshot => ({
    id: 's1',
    measuredAt: '2026-11-10T05:00:00Z',
    reach: 1200,
    views: null,
    likes: 80,
    comments: 6,
    saves: 20,
    shares: 4,
    profileVisits: null,
    follows: null,
    engagement: 0.0917,
    note: null,
    recordedBy: 'Admin',
    ...overrides,
});

describe('formatRate', () => {
    it('shows engagement as an Indonesian percentage', () => {
        expect(formatRate(0.0917)).toBe('9,2%');
        expect(formatRate(null)).toBe('—');
    });
});

describe('ContentInsightPanel', () => {
    it('shows the latest reading with every metric in words, and keeps earlier readings', () => {
        const wrapper = mount(ContentInsightPanel, {
            props: { itemId: 'i', insights: [snapshot(), snapshot({ id: 's0', reach: 500, engagement: 0.05, note: 'Hari ke-1' })] },
        });

        expect(wrapper.text()).toContain('Engagement');
        expect(wrapper.text()).toContain('9,2%');
        expect(wrapper.text()).toContain('Kunjungan profil');
        expect(wrapper.text()).toContain('1.200');
        expect(wrapper.text()).toContain('Bacaan sebelumnya (1)');
        expect(wrapper.text()).toContain('Hari ke-1');
    });

    it('explains what to do when nothing is recorded yet', () => {
        const wrapper = mount(ContentInsightPanel, { props: { itemId: 'i', insights: [] } });

        expect(wrapper.text()).toContain('Belum ada insight.');
        expect(wrapper.find('dl').exists()).toBe(false);
    });
});
