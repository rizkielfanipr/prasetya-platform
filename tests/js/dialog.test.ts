import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';

afterEach(() => {
    document.body.innerHTML = '';
});

function mountDialog(props: Record<string, unknown> = {}) {
    return mount(ConfirmDialog, {
        attachTo: document.body,
        props: { title: 'Periksa?', description: 'Tercatat di audit.', confirmLabel: 'Periksa sekarang', open: true, ...props },
    });
}

describe('ConfirmDialog', () => {
    it('opens as an alert dialog with its name and description, and moves focus inside', async () => {
        mountDialog();
        await flushPromises();

        const dialog = document.body.querySelector('[role="alertdialog"]');
        expect(dialog?.textContent).toContain('Periksa?');
        expect(dialog?.getAttribute('aria-labelledby')).toBeTruthy();
        expect(dialog?.getAttribute('aria-describedby')).toBeTruthy();
        expect(dialog?.contains(document.activeElement)).toBe(true);
    });

    it('emits confirm', async () => {
        const wrapper = mountDialog();
        await flushPromises();

        const confirm = [...document.body.querySelectorAll('button')].find((button) => button.textContent.includes('Periksa sekarang'));
        confirm?.click();

        expect(wrapper.emitted('confirm')).toHaveLength(1);
    });

    it('blocks repeat submission while submitting', async () => {
        mountDialog({ submitting: true });
        await flushPromises();

        const buttons = [...document.body.querySelectorAll('button')];
        expect(buttons.every((button) => button.disabled)).toBe(true);
        expect(buttons.some((button) => button.getAttribute('aria-busy') === 'true')).toBe(true);
    });
});
