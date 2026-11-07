import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test } from './support';

test.beforeEach(async ({ page }) => {
    await login(page);
});

test('dashboard shows live metrics and a lazily loaded chart', async ({ page }) => {
    await expect(page.getByText('Total Pendapatan')).toBeVisible();
    await expect(page.getByTestId('metric-value').first()).toContainText('Rp');
    await expect(page.getByTestId('revenue-canvas')).toBeVisible();
    await expect(page.getByTestId('brand-summary-SATAMU')).toContainText('Satamu Invitation');

    await page.getByText('Lihat data grafik sebagai tabel').click();
    await expect(page.getByRole('table', { name: 'Pendapatan harian per brand' })).toBeVisible();
});

test('dashboard filters by brand through the URL', async ({ page }) => {
    await page.getByRole('group', { name: 'Filter brand grafik' }).getByRole('button', { name: 'Satamu' }).click();
    await expect(page).toHaveURL(/brand=SATAMU/);
    await expect(page.getByRole('table', { name: 'Daftar transaksi' }).getByText('Webworks')).toHaveCount(0);
});

test('search and filter transactions with state kept in the URL', async ({ page }) => {
    await page.goto('/transactions');
    const table = page.getByRole('table', { name: 'Daftar transaksi' });
    await expect(table.locator('tbody tr')).toHaveCount(15);

    await page.getByLabel('Cari invoice atau pelanggan').last().fill('Nadia');
    await expect(page).toHaveURL(/search=Nadia/);
    await expect(table.locator('tbody tr').first()).toContainText('Nadia');
    await expect(table.locator('tbody tr').filter({ hasNotText: 'Nadia' })).toHaveCount(0);

    await page.getByRole('button', { name: 'Hapus semua filter' }).click();
    await expect(page).not.toHaveURL(/search=/);

    await page.locator('#filter-brand').click();
    await page.getByRole('option', { name: 'Satamu' }).click();
    await expect(page).toHaveURL(/brand=SATAMU/);
    await expect(table.getByText('Webworks')).toHaveCount(0);

    await page.reload();
    await expect(table.getByText('Webworks')).toHaveCount(0);
    await expect(page.getByRole('button', { name: 'Hapus filter Brand: Satamu' })).toBeVisible();
});

test('sorts and paginates on the server', async ({ page }) => {
    await page.goto('/transactions');
    await page.getByRole('button', { name: 'Jumlah' }).click();
    await expect(page).toHaveURL(/sort=total_amount/);
    await page.getByRole('button', { name: 'Berikutnya' }).click();
    await expect(page).toHaveURL(/page=2/);
    await expect(page.getByText('Halaman 2 dari')).toBeVisible();
});

test('global search goes to filtered transactions', async ({ page }) => {
    await page.locator('#global-search').fill('Ahmad');
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/transactions\?search=Ahmad/);
});

test('open transaction detail: payment and order status are separate', async ({ page }) => {
    await page.goto('/transactions?payment_status=PAID');
    await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();
    await expect(page).toHaveURL(/\/transactions\/01/);

    await expect(page.getByText('Status pembayaran', { exact: true })).toBeVisible();
    await expect(page.getByText('Status pesanan', { exact: true })).toBeVisible();

    await page.getByRole('tab', { name: /Pembayaran/ }).click();
    await expect(page.getByText('Percobaan 1')).toBeVisible();
    await page
        .getByText(/^Kejadian/)
        .first()
        .click();
    await expect(page.getByText(/Status berubah: Menunggu → Berhasil/).first()).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Riwayat webhook' })).toBeVisible();

    await page.getByRole('tab', { name: 'Aktivitas' }).click();
    await expect(page.getByRole('list', { name: 'Aktivitas transaksi' })).toContainText('Pembayaran tercatat lunas');
    await expect(page.locator('body')).not.toContainText('fake-key-not-real');

    await page.getByRole('tab', { name: 'Ringkasan' }).click();
    await page.getByLabel('Tambah catatan').fill('Catatan dari browser test');
    await page.getByRole('button', { name: 'Simpan' }).click();
    await expect(page.getByText('Catatan dari browser test')).toBeVisible();
});

test('inspect payment events with sanitised diagnostics', async ({ page }) => {
    await page.goto('/payment-events');
    await expect(page.getByRole('table', { name: 'Event pembayaran dari gateway' })).toBeVisible();

    await page.locator('#filter-status').click();
    await page.getByRole('option', { name: 'Ditolak' }).click();
    await expect(page).toHaveURL(/status=REJECTED/);
    await expect(page.getByText('Tanda tangan tidak valid').first()).toBeVisible();

    await page.goto('/payment-events?status=PROCESSED');
    await page.getByText('Lihat', { exact: true }).first().click();
    await expect(page.locator('pre').first()).toContainText('merchantOrderId');
    await expect(page.locator('body')).not.toContainText('fake-key-not-real');
});

test('view a reconciliation issue and run an audited gateway check', async ({ page }) => {
    await page.goto('/reconciliation');
    await page.getByTestId('group-amount_mismatch').click();
    await expect(page).toHaveURL(/group=amount_mismatch/);
    const firstRow = page.getByRole('table', { name: 'Isu rekonsiliasi' }).locator('tbody tr').first();
    await expect(firstRow).toContainText('Jumlah tidak cocok');

    const trigger = firstRow.getByRole('button', { name: 'Cek status' });
    await trigger.click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toBeVisible();

    // Escape closes the dialog and returns focus to what opened it.
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(trigger).toBeFocused();

    await trigger.click();
    await page.getByLabel('Alasan (opsional)').fill('uji browser');
    await page.getByRole('button', { name: 'Periksa sekarang' }).click();
    await expect(page.getByText('Tidak ada data yang berubah').first()).toBeVisible();

    await page.goto('/audit-log?search=payment.gateway_checked');
    await expect(page.getByRole('table', { name: 'Audit log' })).toContainText('uji browser');
});

test('settings show gateway status without credentials', async ({ page }) => {
    await page.goto('/settings');
    await expect(page.getByTestId('gateway-status')).toContainText('Gateway fake');
    await expect(page.locator('body')).not.toContainText('e2e-secret-not-real');
    // Users moved to their own screen (Pengguna).
    await expect(page.getByRole('table', { name: 'Pengguna admin' })).toHaveCount(0);
});

for (const path of ['/dashboard', '/transactions', '/payment-events', '/reconciliation', '/audit-log', '/users', '/settings']) {
    test(`no accessibility violations on ${path}`, async ({ page }) => {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
        await expectNoAxeViolations(page);
    });
}

test('no accessibility violations on transaction detail', async ({ page }) => {
    await page.goto('/transactions?payment_status=PAID');
    await page.getByRole('table', { name: 'Daftar transaksi' }).locator('tbody a').first().click();
    await page.waitForLoadState('networkidle');
    await expectNoAxeViolations(page);
});
