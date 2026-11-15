import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test } from './support';

// ADR-0010 and ADR-0016: one transaction is investigated and checked from its single detail page.
test.beforeEach(async ({ page }) => {
    await login(page);
});

test('investigate a pending payment and run Cek status from its attempt; the check shows in Riwayat', async ({ page }) => {
    await page.goto('/transactions?payment_status=PENDING');
    await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();
    await expect(page).toHaveURL(/\/transactions\/01/);
    const detailUrl = page.url();

    const attempt = page.getByRole('article').filter({ has: page.getByRole('heading', { name: 'Percobaan 1' }) });
    await attempt.getByRole('button', { name: 'Cek status' }).click();
    const dialog = page.getByRole('alertdialog');
    await dialog.getByLabel('Alasan (opsional)').fill('investigasi browser');
    await dialog.getByRole('button', { name: 'Periksa sekarang' }).click();
    await expect(dialog).toBeHidden();

    // Back on the same page, with the outcome and the last check recorded.
    await expect(page).toHaveURL(detailUrl);
    await expect(page.getByTestId('last-gateway-check').first()).toBeVisible();

    const history = page.getByRole('list', { name: 'Riwayat transaksi' });
    await expect(history).toContainText('Status diperiksa ke gateway');
    await expect(history).toContainText('Alasan: investigasi browser');
});

test('an old ?tab= link opens the same single page', async ({ page }) => {
    await page.goto('/transactions');
    await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();
    await page.waitForURL(/\/transactions\/01/);
    await page.goto(`${page.url()}?tab=activity`);

    await expect(page.getByRole('tab')).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Riwayat' })).toBeVisible();
});

test('an order without checkout explains the empty payment section', async ({ page }) => {
    await page.goto('/transactions?payment_status=NONE&sort=created_at&direction=asc'); // Oldest: past the no-attempt threshold.
    await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();

    await expect(page.getByText('Belum ada percobaan pembayaran')).toBeVisible();
    await expect(page.getByTestId('transaction-issues')).toContainText('Pesanan tanpa percobaan bayar');
    await expect(page.getByTestId('transaction-issues')).toContainText('Informasi');
});

test('the transaction detail has no accessibility violations', async ({ page }) => {
    for (const status of ['PAID', 'PENDING', 'NONE']) {
        await page.goto(`/transactions?payment_status=${status}`);
        await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();
        await page.waitForLoadState('networkidle');
        await expectNoAxeViolations(page);
    }
});
