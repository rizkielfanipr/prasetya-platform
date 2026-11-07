import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test, USERS } from './support';

// ADR-0010, phase 3: user audit on the user's page, system audit under Pengaturan.
test.beforeEach(async ({ page }) => {
    await login(page);
});

test("open a user's activity from the user list", async ({ page }) => {
    await page.goto('/users');
    await page.getByRole('link', { name: 'Admin Demo' }).click();
    await expect(page).toHaveURL(/\/users\/01/);
    await expect(page.getByRole('heading', { level: 1, name: 'Admin Demo' })).toBeVisible();

    const activity = page.getByRole('list', { name: 'Riwayat audit' });
    await expect(activity).toContainText('Masuk');
    await expect(page.locator('body')).not.toContainText('password');
});

test('open Aktivitas Sistem from Pengaturan and widen it to every entry', async ({ page }) => {
    await page.goto('/settings');
    await page.getByRole('link', { name: 'Buka aktivitas sistem' }).click();
    await expect(page).toHaveURL(/\/settings\/activity/);

    const table = page.getByRole('table', { name: 'Audit log' });
    await expect(table).toContainText('auth.login');
    await expect(table).not.toContainText('payment.gateway_checked');

    await page.getByLabel('Lingkup').click();
    await page.getByRole('option', { name: 'Semua audit' }).click();
    await expect(page).toHaveURL(/scope=all/);
    await expect(page.getByRole('button', { name: 'Hapus filter Lingkup: Semua audit' })).toBeVisible();

    await page.locator('#audit-search').fill(USERS.admin);
    await expect(page).toHaveURL(/search=demo/);
});

test('the user detail and system activity have no accessibility violations', async ({ page }) => {
    await page.goto('/users');
    await page.getByRole('link', { name: 'Admin Demo' }).click();
    await page.waitForLoadState('networkidle');
    await expectNoAxeViolations(page);

    await page.goto('/settings/activity');
    await page.waitForLoadState('networkidle');
    await expectNoAxeViolations(page);
});
