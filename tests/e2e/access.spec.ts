import { expect } from '@playwright/test';
import { login, test, USERS } from './support';

// One access level (ADR-0009): any active user sees and uses everything.
test('a colleague sees every menu item, the evidence, and the settings panels', async ({ page }) => {
    await login(page, USERS.colleague);

    const nav = page.getByRole('navigation', { name: 'Navigasi utama' });
    for (const item of ['Dashboard', 'Transaksi', 'Event Pembayaran', 'Rekonsiliasi', 'Audit Log', 'Pengaturan']) {
        await expect(nav.getByRole('link', { name: item })).toBeVisible();
    }

    await page.goto('/transactions?payment_status=PAID');
    await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();
    await expect(page.getByRole('heading', { name: 'Riwayat webhook' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Riwayat audit' })).toBeVisible();

    await page.goto('/reconciliation');
    await expect(page.getByRole('heading', { level: 1, name: 'Rekonsiliasi' })).toBeVisible();

    await page.goto('/settings');
    await expect(page.getByRole('heading', { name: 'Koneksi gateway' })).toBeVisible();
});
