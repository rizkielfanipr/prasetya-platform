import { type Browser, expect, type Page } from '@playwright/test';
import { expectNoAxeViolations, login, logout, test, USERS } from './support';

const INITIAL = 'initial-password-1';

/** A username no other test or run has used: the e2e database is shared across tests. */
const uniqueUsername = (prefix: string) => `${prefix}-${Date.now().toString(36)}`;

async function createUser(page: Page, username: string, name = 'Rekan Uji'): Promise<void> {
    await page.goto('/users');
    await page.getByRole('button', { name: 'Tambah pengguna' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Tambah pengguna' });
    await dialog.getByLabel('Nama').fill(name);
    await dialog.getByLabel('Username').fill(username);
    await dialog.getByLabel('Kata sandi awal').fill(INITIAL);
    await dialog.getByLabel('Ulangi kata sandi').fill(INITIAL);
    await dialog.getByRole('button', { name: 'Tambah pengguna' }).click();
    await expect(dialog).toBeHidden();
    await expect(page.getByRole('table', { name: 'Daftar pengguna' })).toContainText(username);
}

/** Opens a row's "Aksi" menu and chooses an item. */
async function rowAction(page: Page, username: string, item: string): Promise<void> {
    await page.getByRole('button', { name: `Aksi untuk ${username}`, exact: true }).click();
    await page.getByRole('menuitem', { name: item }).click();
}

async function signInElsewhere(browser: Browser, username: string, password: string): Promise<Page> {
    const page = await (await browser.newContext()).newPage();
    await page.goto('/login');
    await page.getByLabel('Username').fill(username);
    await page.getByLabel('Kata sandi', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Masuk' }).click();
    return page;
}

test.beforeEach(async ({ page }) => {
    await login(page, USERS.admin);
});

test('a new colleague can sign in with their username and open every menu, including Pengguna', async ({ page }) => {
    const username = uniqueUsername('rekan');
    await createUser(page, username);
    await logout(page);

    // Usernames are case-insensitive at sign-in.
    await page.getByLabel('Username').fill(username.toUpperCase());
    await page.getByLabel('Kata sandi', { exact: true }).fill(INITIAL);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
    const nav = page.getByRole('navigation', { name: 'Navigasi utama' });
    await expect(nav.getByRole('link', { name: 'Pengguna' })).toBeVisible();
    await nav.getByRole('link', { name: 'Pengguna' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Pengguna' })).toBeVisible();
});

test('deactivating a signed-in user sends their next request to the login page', async ({ page, browser }) => {
    const username = uniqueUsername('nonaktif');
    await createUser(page, username);
    const theirs = await signInElsewhere(browser, username, INITIAL);
    await expect(theirs).toHaveURL(/\/dashboard/);

    await rowAction(page, username, 'Nonaktifkan');
    const confirm = page.getByRole('alertdialog', { name: `Nonaktifkan ${username}?` });
    await expect(confirm).toContainText('langsung keluar');
    await confirm.getByRole('button', { name: 'Nonaktifkan' }).click();
    await expect(page.getByRole('row').filter({ hasText: username })).toContainText('Nonaktif');

    await theirs.goto('/transactions');
    await expect(theirs).toHaveURL(/\/login/);
    await theirs.context().close();
});

test('a password set by a colleague replaces the old one', async ({ page, browser }) => {
    const username = uniqueUsername('sandi');
    await createUser(page, username);

    await rowAction(page, username, 'Atur kata sandi');
    const dialog = page.getByRole('alertdialog', { name: `Atur kata sandi ${username}` });
    await dialog.getByLabel('Kata sandi baru', { exact: true }).fill('replaced-password-1');
    await dialog.getByLabel('Ulangi kata sandi baru').fill('replaced-password-1');
    await dialog.getByRole('button', { name: 'Atur kata sandi' }).click();
    await expect(dialog).toBeHidden();

    const withNew = await signInElsewhere(browser, username, 'replaced-password-1');
    await expect(withNew).toHaveURL(/\/dashboard/);
    await withNew.context().close();
});

test.describe('refused input', () => {
    test.use({ allowedConsole: [/422/] });

    test('shows validation errors in the dialog and keeps it open', async ({ page }) => {
        await page.goto('/users');
        await page.getByRole('button', { name: 'Tambah pengguna' }).click();
        const dialog = page.getByRole('alertdialog', { name: 'Tambah pengguna' });
        await dialog.getByLabel('Nama').fill('Tidak Valid');
        await dialog.getByLabel('Username').fill(USERS.admin);
        await dialog.getByLabel('Kata sandi awal').fill('short');
        await dialog.getByRole('button', { name: 'Tambah pengguna' }).click();

        await expect(dialog.getByRole('alert').filter({ hasText: 'Username ini sudah dipakai' })).toBeVisible();
        await expect(dialog.getByLabel('Username')).toHaveAttribute('aria-invalid', 'true');
        await expect(dialog).toBeVisible();
    });
});

test('your own row cannot be deactivated, and says why', async ({ page }) => {
    await page.goto('/users');
    await page.getByRole('button', { name: `Aksi untuk ${USERS.admin}`, exact: true }).click();
    const menu = page.getByRole('menu');
    await expect(menu.getByRole('menuitem', { name: 'Nonaktifkan' })).toHaveAttribute('aria-disabled', 'true');
    await expect(menu.getByRole('menuitem', { name: 'Nonaktifkan' })).toHaveAccessibleDescription('Anda tidak dapat menonaktifkan akun sendiri.');
    await expect(menu.getByRole('menuitem', { name: 'Atur kata sandi' })).toHaveCount(0);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('button', { name: `Aksi untuk ${USERS.admin}`, exact: true })).toBeFocused();
});

test('the users screen and the create dialog have no accessibility violations', async ({ page }) => {
    await page.goto('/users');
    await page.waitForLoadState('networkidle');
    await expectNoAxeViolations(page);

    await page.getByRole('button', { name: 'Tambah pengguna' }).click();
    const dialog = page.getByRole('alertdialog');
    await expect(dialog).toBeVisible();
    // Measure contrast once the fade-in has finished, not halfway through it.
    await dialog.evaluate((element) => Promise.all(element.getAnimations({ subtree: true }).map((animation) => animation.finished)));
    await expectNoAxeViolations(page);
});

test('a deactivated colleague cannot sign in', async ({ page, browser }) => {
    const username = uniqueUsername('ditolak');
    await createUser(page, username);
    await rowAction(page, username, 'Nonaktifkan');
    await page.getByRole('alertdialog').getByRole('button', { name: 'Nonaktifkan' }).click();
    await expect(page.getByRole('row').filter({ hasText: username })).toContainText('Nonaktif');

    const theirs = await signInElsewhere(browser, username, INITIAL);
    await expect(theirs.getByRole('alert').filter({ hasText: 'Username atau kata sandi salah' })).toBeVisible();
    await theirs.context().close();
});
