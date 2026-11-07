import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, logout, PASSWORD, test, USERS } from './support';

test('admin login and logout', async ({ page }) => {
    await login(page);
    await expect(page.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();
    await logout(page);
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login/);
});

test.describe('invalid login', () => {
    test.use({ allowedConsole: [/422/] });

    test('shows an announced field error and stays on login', async ({ page }) => {
        await page.goto('/login');
        await page.getByLabel('Username').fill(USERS.admin);
        await page.getByLabel('Kata sandi', { exact: true }).fill('wrong-password');
        await page.getByRole('button', { name: 'Masuk' }).click();

        const error = page.getByRole('alert').filter({ hasText: 'Username atau kata sandi salah' });
        await expect(error).toBeVisible();
        await expect(page.getByLabel('Username')).toHaveAttribute('aria-invalid', 'true');
        await expect(page).toHaveURL(/\/login/);
    });
});

test('dashboard is protected', async ({ page }) => {
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login/);
});

test('password can be shown and hidden with an accessible control', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Kata sandi', { exact: true }).fill(PASSWORD);
    await page.getByRole('button', { name: 'Tampilkan kata sandi' }).click();
    await expect(page.getByLabel('Kata sandi', { exact: true })).toHaveAttribute('type', 'text');
    await page.getByRole('button', { name: 'Sembunyikan kata sandi' }).click();
    await expect(page.getByLabel('Kata sandi', { exact: true })).toHaveAttribute('type', 'password');
});

test('login can be completed with the keyboard alone', async ({ page }) => {
    await page.goto('/login');
    await expect(page.getByLabel('Username')).toBeFocused(); // The form mounts after load; keys sent earlier are lost.
    await page.keyboard.type(USERS.admin);
    await page.keyboard.press('Tab');
    await page.keyboard.type(PASSWORD);
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/\/dashboard/);
});

test('login page has no accessibility violations', async ({ page }) => {
    await page.goto('/login');
    await expectNoAxeViolations(page);
});
