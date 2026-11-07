import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test } from './support';

// ADR-0010, phase 2: Transaksi → Semua, Perlu Ditinjau, Event Pembayaran.
const groups = [
    'orders_without_attempt',
    'orders_without_success',
    'unmatched_payments',
    'amount_mismatch',
    'rejected_webhooks',
    'duplicate_webhooks',
    'pending_too_long',
    'paid_inconsistent',
    'invalid_transitions',
];

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
    await page.getByTestId('workspace-events').click();
    await expect(page).toHaveURL(/view=events/);
    await expect(page.getByRole('table', { name: 'Event pembayaran dari gateway' })).toBeVisible();

    await page.goBack();
    await expect(page).toHaveURL(/view=attention/);
    await expect(page.getByRole('navigation', { name: 'Kelompok isu' })).toBeVisible();
});

test('Perlu Ditinjau opens each of the nine groups', async ({ page }) => {
    await page.goto('/transactions?view=attention');
    await expect(page.getByTestId('attention-total')).toContainText('isu perlu tindakan');

    for (const group of groups) {
        await page.getByTestId(`group-${group}`).click();
        // Laravel sorts query parameters, so check each one rather than their order.
        await expect(page).toHaveURL(new RegExp(`group=${group}`));
        await expect(page).toHaveURL(/view=attention/);
        await expect(page.getByTestId(`group-${group}`)).toHaveAttribute('aria-current', 'true');
        await expect(page.getByRole('table', { name: 'Isu rekonsiliasi' })).toBeVisible();
    }
});

test('an issue without a transaction says so instead of linking nowhere', async ({ page }) => {
    await page.goto('/transactions?view=attention&group=unmatched_payments');
    const row = page.getByRole('table', { name: 'Isu rekonsiliasi' }).locator('tbody tr').first();
    await expect(row.getByTestId('no-transaction')).toHaveText('Transaksi tidak ditemukan');
    await expect(row.locator('a')).toHaveCount(0);
});

test('Event Pembayaran finds rejected, duplicate, and processed events', async ({ page }) => {
    await page.goto('/transactions?view=events');
    for (const [status, label] of [
        ['REJECTED', 'Ditolak'],
        ['DUPLICATE', 'Duplikat'],
        ['PROCESSED', 'Diproses'],
    ] as const) {
        await page.getByLabel('Pemrosesan').click();
        await page.getByRole('option', { name: label, exact: true }).click();
        await expect(page).toHaveURL(new RegExp(`status=${status}`));
        await expect(page).toHaveURL(/view=events/);
        await expect(page.getByRole('table', { name: 'Event pembayaran dari gateway' }).locator('tbody tr').first()).toContainText(label);
    }
});

test('the sidebar shows open issues on Transaksi and the bell opens Perlu Ditinjau', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page.getByRole('navigation', { name: 'Navigasi utama' }).getByTestId('sidebar-issue-badge')).toBeVisible();
    await page.getByRole('link', { name: /Rekonsiliasi: \d+ isu/ }).click();
    await expect(page).toHaveURL(/\/transactions\?view=attention/);
});

for (const view of ['all', 'attention', 'events']) {
    test(`the ${view} view has no accessibility violations`, async ({ page }) => {
        await page.goto(`/transactions?view=${view}`);
        await page.waitForLoadState('networkidle');
        await expectNoAxeViolations(page);
    });
}
