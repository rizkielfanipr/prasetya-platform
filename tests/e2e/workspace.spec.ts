import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test } from './support';

// ADR-0010 and ADR-0016: Transaksi → Semua and Perlu Ditinjau (four queues); the webhook log is Pengaturan → Log Gateway.
// The demo data has a rejected signature, so the system queue is listed too.
const queues = ['unmatched', 'amount_mismatch', 'waiting', 'system'];

test.beforeEach(async ({ page }) => {
    await login(page);
});

test('search an invoice in Semua and open its detail', async ({ page }) => {
    await page.goto('/transactions');
    await expect(page.getByTestId('workspace-all')).toHaveAttribute('aria-current', 'page');
    const table = page.getByRole('table', { name: 'Daftar transaksi' });
    const invoice = (await table.locator('tbody a').first().textContent())?.trim() ?? '';

    await page.locator('#transaction-search').fill(invoice);
    await expect(page).toHaveURL(new RegExp(`search=${invoice}`));
    await expect(page).toHaveURL(/view=all/);
    await table.locator('tbody a').first().click();
    await expect(page.getByRole('heading', { level: 1, name: invoice })).toBeVisible();
});

test('switching views changes the URL and the history', async ({ page }) => {
    await page.goto('/transactions');
    await page.getByTestId('workspace-attention').click();
    await expect(page).toHaveURL(/view=attention/);
    await expect(page.getByTestId('workspace-attention')).toHaveAttribute('aria-current', 'page');
    await expect(page.getByTestId('workspace-events')).toHaveCount(0);

    await page.goBack();
    await expect(page).toHaveURL(/\/transactions$|view=all/);
    await page.goForward();
    await expect(page.getByRole('navigation', { name: 'Antrean tinjauan' })).toBeVisible();
});

test('Perlu Ditinjau opens each review queue', async ({ page }) => {
    await page.goto('/transactions?view=attention');
    await expect(page.getByTestId('attention-total')).toContainText('isu menunggu keputusan');

    for (const queue of queues) {
        await page.getByTestId(`queue-${queue}`).click();
        // Laravel sorts query parameters, so check each one rather than their order.
        await expect(page).toHaveURL(new RegExp(`queue=${queue}`));
        await expect(page).toHaveURL(/view=attention/);
        await expect(page.getByTestId(`queue-${queue}`)).toHaveAttribute('aria-current', 'true');
        await expect(page.locator('#queue-heading')).toBeVisible();
    }
});

test('old group links land on their queue or their new home', async ({ page }) => {
    await page.goto('/reconciliation?group=pending_too_long');
    await expect(page.getByTestId('queue-waiting')).toHaveAttribute('aria-current', 'true');
    await page.goto('/reconciliation?group=orders_without_attempt');
    await expect(page).toHaveURL(/payment_status=NONE/);
    await expect(page.getByRole('button', { name: /Pembayaran: No Payment/ })).toBeVisible();
});

test('an issue without a transaction says so instead of linking nowhere', async ({ page }) => {
    await page.goto('/transactions?view=attention&queue=unmatched');
    const row = page.getByRole('table', { name: 'Uang tanpa pesanan' }).locator('tbody tr').first();
    await expect(row.getByTestId('no-transaction')).toHaveText('Transaksi tidak ditemukan');
    await expect(row.locator('a')).toHaveCount(0);
});

test('Log Gateway finds rejected, duplicate, and processed events', async ({ page }) => {
    await page.goto('/settings');
    await page.getByTestId('open-gateway-log').click();
    await expect(page).toHaveURL(/\/settings\/gateway-log/);
    for (const [status, label] of [
        ['REJECTED', 'Rejected'],
        ['DUPLICATE', 'Duplicate'],
        ['PROCESSED', 'Processed'],
    ] as const) {
        await page.getByLabel('Pemrosesan').click();
        await page.getByRole('option', { name: label, exact: true }).click();
        await expect(page).toHaveURL(new RegExp(`status=${status}`));
        await expect(page).toHaveURL(/\/settings\/gateway-log/);
        await expect(page.getByRole('table', { name: 'Event pembayaran dari gateway' }).locator('tbody tr').first()).toContainText(label);
    }
});

test('the sidebar shows open issues on Transaksi and the bell opens Perlu Ditinjau', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page.getByRole('navigation', { name: 'Navigasi utama' }).getByTestId('sidebar-issue-badge')).toBeVisible();
    await page.getByRole('link', { name: /Rekonsiliasi: \d+ isu/ }).click();
    await expect(page).toHaveURL(/\/transactions\?view=attention/);
});

for (const path of ['/transactions?view=all', '/transactions?view=attention', '/transactions?view=attention&queue=system', '/settings/gateway-log']) {
    test(`${path} has no accessibility violations`, async ({ page }) => {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
        await expectNoAxeViolations(page);
    });
}
