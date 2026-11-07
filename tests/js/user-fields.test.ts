import UserFields from '@/features/users/UserFields.vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

function mountFields(errors: Partial<Record<'name' | 'username' | 'password', string>> = {}) {
    return mount(UserFields, { props: { errors, name: '', username: '', password: '', confirmation: '' } });
}

describe('UserFields', () => {
    it('labels every field and explains the username and password rules', () => {
        const wrapper = mountFields();

        for (const label of ['Nama', 'Username', 'Kata sandi awal', 'Ulangi kata sandi']) {
            expect(wrapper.text()).toContain(label);
        }
        const username = wrapper.get('#new-user-username');
        expect(username.attributes('aria-describedby')).toBe('new-user-username-hint');
        expect(wrapper.get('#new-user-username-hint').text()).toContain('Tidak dapat diubah setelah dibuat');
        expect(wrapper.get('#new-user-password-hint').text()).toContain('Minimal 12 karakter');
    });

    it('shows each server error under its field, announced and linked to the input', () => {
        const wrapper = mountFields({ name: 'Nama wajib diisi.', username: 'Username ini sudah dipakai.', password: 'Kata sandi terlalu pendek.' });

        for (const [id, message] of [
            ['new-user-name', 'Nama wajib diisi.'],
            ['new-user-username', 'Username ini sudah dipakai.'],
            ['new-user-password', 'Kata sandi terlalu pendek.'],
        ] as const) {
            const input = wrapper.get(`#${id}`);
            const error = wrapper.get(`#${id}-error`);
            expect(error.text()).toBe(message);
            expect(error.attributes('role')).toBe('alert');
            expect(input.attributes('aria-describedby')).toBe(`${id}-error`);
            expect(input.attributes('aria-invalid')).toBe('true');
        }
        // The hint gives way to the error, so only one message is read.
        expect(wrapper.find('#new-user-username-hint').exists()).toBe(false);
    });

    it('updates its models as the user types', async () => {
        const wrapper = mountFields();

        await wrapper.get('#new-user-username').setValue('rekan');

        expect(wrapper.emitted('update:username')?.[0]).toEqual(['rekan']);
    });
});
