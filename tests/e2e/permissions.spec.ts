import { expect } from '@playwright/test';
import { login, test, USERS } from './support';

test.use({ allowedConsole: [/403/] });

test('operations cannot open finance screens, by URL or by navigation', async ({ page }) => {
    await login(page, USERS.operations);

    const nav = page.getByRole('navigation', { name: 'Navigasi utama' });
    await expect(nav.getByRole('link', { name: 'Transaksi' })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Audit Log' })).toHaveCount(0);
    await expect(nav.getByRole('link', { name: 'Rekonsiliasi' })).toHaveCount(0);

    const response = await page.goto('/audit-log');
    expect(response?.status()).toBe(403);
    await expect(page.getByTestId('error-state')).toContainText('Akses ditolak');

    await page.goto('/transactions?payment_status=PAID');
    await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();
    await expect(page.getByRole('heading', { name: 'Riwayat webhook' })).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Riwayat audit' })).toHaveCount(0);
});

test('finance can reconcile but not manage settings', async ({ page }) => {
    await login(page, USERS.finance);
    await page.goto('/reconciliation');
    await expect(page.getByRole('heading', { level: 1, name: 'Rekonsiliasi' })).toBeVisible();
    await page.goto('/settings');
    await expect(page.getByRole('heading', { name: 'Koneksi DOKU' })).toHaveCount(0);
});
