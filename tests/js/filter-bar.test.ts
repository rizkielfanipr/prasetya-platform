import FilterBar from '@/components/shared/FilterBar.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('FilterBar', () => {
    it('shows active filters that can be removed by name', async () => {
        const wrapper = mount(FilterBar, { props: { active: [{ key: 'brand', label: 'Brand: Satamu' }] } });
        const chip = wrapper.get('[aria-label="Hapus filter Brand: Satamu"]');

        await chip.trigger('click');

        expect(wrapper.emitted('remove')).toEqual([['brand']]);
    });

    it('clears everything', async () => {
        const wrapper = mount(FilterBar, { props: { active: [{ key: 'a', label: 'A' }] } });
        await wrapper.findAll('button')[1]?.trigger('click');

        expect(wrapper.emitted('clear')).toHaveLength(1);
    });

    it('hides the chip row when nothing is active', () => {
        expect(
            mount(FilterBar, { props: { active: [] } })
                .find('[data-testid="active-filters"]')
                .exists(),
        ).toBe(false);
    });
});
