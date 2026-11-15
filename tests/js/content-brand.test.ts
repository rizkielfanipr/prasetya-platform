import ContentComposition from '@/features/content/ContentComposition.vue';
import { linesToList } from '@/lib/content';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('linesToList', () => {
    it('keeps one trimmed entry per non-blank line', () => {
        expect(linesToList(' murah \n\n termurah\n')).toEqual(['murah', 'termurah']);
    });
});

describe('ContentComposition', () => {
    it('states each pillar as count, share, and target in text', () => {
        const wrapper = mount(ContentComposition, {
            props: {
                entries: [
                    {
                        brand: { code: 'WEBWORKS', name: 'Prasetya Webworks' },
                        total: 4,
                        unassigned: 1,
                        pillars: [
                            { id: 'a', name: 'Edukasi', targetShare: 60, count: 2 },
                            { id: 'b', name: 'Promo', targetShare: 40, count: 1 },
                        ],
                    },
                ],
            },
        });

        // The separator is a drawn dot with a comma for screen readers.
        expect(wrapper.get('[data-testid="composition-Edukasi"]').text()).toContain('2, 50% / target 60%');
        expect(wrapper.get('[data-testid="composition-Promo"]').text()).toContain('1, 25% / target 40%');
        expect(wrapper.text()).toContain('(4 konten, 1 tanpa pilar)');
    });
});

describe('ContentComposition for labels', () => {
    it('leaves out the target of a pillar added by name, which has none', () => {
        const wrapper = mount(ContentComposition, {
            props: {
                entries: [
                    {
                        brand: { code: 'WEBWORKS', name: 'Prasetya Webworks' },
                        total: 2,
                        unassigned: 0,
                        pillars: [{ id: 'a', name: 'Baru', targetShare: 0, count: 2 }],
                    },
                ],
            },
        });

        expect(wrapper.get('[data-testid="composition-Baru"]').text()).not.toContain('target');
    });
});
