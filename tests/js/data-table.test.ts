import DataTable from '@/components/shared/DataTable.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

const columns = [
    { key: 'name', label: 'Nama', sortable: true },
    { key: 'menu', label: 'Aksi', srOnlyLabel: true },
];
const base = { caption: 'Contoh', columns, rows: [{ id: 1, name: 'Satu' }], rowKey: (row: unknown) => (row as { id: number }).id };

describe('DataTable', () => {
    it('renders real table headers and a caption', () => {
        const wrapper = mount(DataTable, { props: base });

        expect(wrapper.find('caption').text()).toBe('Contoh');
        expect(wrapper.findAll('th[scope="col"]')).toHaveLength(2);
        expect(wrapper.findAll('th')[1]?.find('.sr-only').text()).toBe('Aksi');
    });

    it('renders cells through named slots', () => {
        const wrapper = mount(DataTable, { props: base, slots: { 'cell-name': '<template #cell-name="{ row }">{{ row.name }}</template>' } });

        expect(wrapper.find('tbody').text()).toContain('Satu');
    });

    it('exposes sort state and emits sort', async () => {
        const wrapper = mount(DataTable, { props: { ...base, sort: 'name', direction: 'asc' as const } });

        expect(wrapper.find('th').attributes('aria-sort')).toBe('ascending');
        await wrapper.find('th button').trigger('click');
        expect(wrapper.emitted('sort')).toEqual([['name']]);
    });

    it('shows skeletons, then empty, then error as distinct states', async () => {
        const wrapper = mount(DataTable, { props: { ...base, rows: [], loading: true }, slots: { empty: '<p>Kosong</p>' } });
        expect(wrapper.find('[data-slot="skeleton"]').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('Kosong');

        await wrapper.setProps({ loading: false });
        expect(wrapper.text()).toContain('Kosong');

        await wrapper.setProps({ failed: true });
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        expect(wrapper.find('table').exists()).toBe(false);
    });
});
