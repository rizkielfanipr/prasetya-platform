import { expect } from '@playwright/test';
import { login, test, USERS } from './support';

// One access level (ADR-0009): any active user sees and uses everything.
test('a colleague sees every menu item, the evidence, and the settings panels', async ({ page }) => {
    await login(page, USERS.colleague);

    const nav = page.getByRole('navigation', { name: 'Navigasi utama' });
    for (const item of ['Dashboard', 'Transaksi', 'Pengguna', 'Pengaturan']) {
        await expect(nav.getByRole('link', { name: item })).toBeVisible();
    }
    for (const removed of ['Event Pembayaran', 'Rekonsiliasi', 'Audit Log']) {
        await expect(nav.getByRole('link', { name: removed })).toHaveCount(0);
    }

    await page.goto('/transactions?payment_status=PAID');
    await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();
    await page.getByRole('tab', { name: /Pembayaran/ }).click();
    await expect(page.getByRole('heading', { name: 'Riwayat webhook' })).toBeVisible();
    await page.getByRole('tab', { name: 'Aktivitas' }).click();
    await expect(page.getByRole('list', { name: 'Aktivitas transaksi' })).toBeVisible();

    await page.goto('/reconciliation?group=amount_mismatch');
    await expect(page).toHaveURL(
        (url) => url.pathname === '/transactions' && url.searchParams.get('view') === 'attention' && url.searchParams.get('group') === 'amount_mismatch',
    );
    await expect(page.getByTestId('workspace-attention')).toHaveAttribute('aria-current', 'page');

    await page.goto('/settings');
    await expect(page.getByRole('heading', { name: 'Koneksi gateway' })).toBeVisible();
});
