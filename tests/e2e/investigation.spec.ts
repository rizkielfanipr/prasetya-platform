import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test } from './support';

// ADR-0010, phase 1: one transaction can be investigated and checked from its detail page.
test.beforeEach(async ({ page }) => {
    await login(page);
});

test('investigate a pending payment and run Cek status from its attempt; the check shows in Aktivitas', async ({ page }) => {
    await page.goto('/transactions?payment_status=PENDING');
    await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();
    await expect(page).toHaveURL(/\/transactions\/01/);

    await page.getByRole('tab', { name: /Pembayaran/ }).click();
    await expect(page).toHaveURL(/tab=payments/);
    await expect(page.getByRole('heading', { name: 'Percobaan 1' })).toBeVisible();

    const attempt = page.getByRole('article').filter({ has: page.getByRole('heading', { name: 'Percobaan 1' }) });
    await attempt.getByRole('button', { name: 'Cek status' }).click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByLabel('Alasan (opsional)').fill('investigasi browser');
    await dialog.getByRole('button', { name: 'Periksa sekarang' }).click();
    await expect(dialog).toBeHidden();

    // Back on the same tab, with the outcome and the last check recorded.
    await expect(page).toHaveURL(/tab=payments/);
    await expect(page.getByTestId('last-gateway-check').first()).toBeVisible();

    await page.getByRole('tab', { name: 'Aktivitas' }).click();
    const activity = page.getByRole('list', { name: 'Aktivitas transaksi' });
    await expect(activity).toContainText('Status diperiksa ke gateway');
    await expect(activity).toContainText('Alasan: investigasi browser');
});

test('the detail tabs work with the keyboard and keep the tab in the URL', async ({ page }) => {
    await page.goto('/transactions');
    await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();

    await page.getByRole('tab', { name: 'Ringkasan' }).focus();
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('tab', { name: /Pembayaran/ })).toBeFocused();
    await expect(page.getByRole('tab', { name: /Pembayaran/ })).toHaveAttribute('aria-selected', 'true');
    await page.keyboard.press('ArrowRight');
    await expect(page.getByRole('tab', { name: 'Aktivitas' })).toHaveAttribute('aria-selected', 'true');
    await expect(page).toHaveURL(/tab=activity/);

    await page.reload();
    await expect(page.getByRole('tab', { name: 'Aktivitas' })).toHaveAttribute('aria-selected', 'true');
});

test('an order without checkout explains the empty payment section', async ({ page }) => {
    await page.goto('/reconciliation?group=orders_without_attempt');
    const row = page.getByRole('table', { name: 'Isu rekonsiliasi' }).locator('tbody tr').first();
    await row.getByRole('link').first().click();

    await page.getByRole('tab', { name: /Pembayaran/ }).click();
    await expect(page.getByText('Belum ada percobaan pembayaran')).toBeVisible();
    await expect(page.getByTestId('transaction-issues')).toContainText('Pesanan tanpa percobaan bayar');
});

test('the transaction detail tabs have no accessibility violations', async ({ page }) => {
    await page.goto('/transactions?payment_status=PAID');
    await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();
    for (const tab of ['Ringkasan', /Pembayaran/, 'Aktivitas']) {
        await page.getByRole('tab', { name: tab }).click();
        await page.waitForLoadState('networkidle');
        await expectNoAxeViolations(page);
    }
});
