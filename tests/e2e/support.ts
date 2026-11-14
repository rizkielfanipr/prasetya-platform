import AxeBuilder from '@axe-core/playwright';
import { type ConsoleMessage, expect, type Locator, type Page, test as base } from '@playwright/test';

export const USERS = { admin: 'demo', colleague: 'demo2' } as const;
export const PASSWORD = 'demo-password-only-1';

/** Fails any test that logs a console error, warning, or uncaught exception. Expected HTTP failures are declared per test. */
export const test = base.extend<{ allowedConsole: RegExp[]; consoleGuard: undefined }>({
    allowedConsole: [[], { option: true }],
    consoleGuard: [
        async ({ page, allowedConsole }, use) => {
            const problems: string[] = [];
            page.on('console', (message: ConsoleMessage) => {
                if (['error', 'warning'].includes(message.type()) && !allowedConsole.some((pattern) => pattern.test(message.text()))) {
                    problems.push(`${message.type()}: ${message.text()}`);
                }
            });
            page.on('pageerror', (error) => problems.push(`exception: ${error.message}`));
            await use(undefined);
            expect(problems).toEqual([]);
        },
        { auto: true },
    ],
});

export async function login(page: Page, username: string = USERS.admin): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Username').fill(username);
    await page.getByLabel('Kata sandi', { exact: true }).fill(PASSWORD);
    await page.getByRole('button', { name: 'Masuk' }).click();
    await expect(page).toHaveURL(/\/dashboard/);
}

export async function logout(page: Page): Promise<void> {
    await page.getByTestId('user-menu').click();
    await page.getByTestId('logout').click();
    await expect(page).toHaveURL(/\/login/);
}

export async function expectNoAxeViolations(page: Page): Promise<void> {
    // Contrast is judged on the settled screen: a dialog or toast mid-fade has partial opacity.
    await page.evaluate(() => Promise.allSettled(document.getAnimations().map((animation) => animation.finished)));
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    expect(results.violations.map((v) => ({ id: v.id, nodes: v.nodes.map((n) => n.target.join(' ')).slice(0, 5) }))).toEqual([]);
}

/** Lainnya entries that open a confirmation dialog, and that dialog's name; every other entry opens a sheet of its own name. */
const DETAIL_DIALOGS: Record<string, string | RegExp> = { 'Ubah rencana': 'Ubah rencana', 'Kembali ke Draft': 'Kembalikan ke Draft?', Arsipkan: /^Arsipkan/ };

/** Opens a named secondary surface of the content detail page from Lainnya (ADR-0013) and answers it. */
export async function openDetailSurface(page: Page, name: string): Promise<Locator> {
    await page.getByTestId('detail-menu').click();
    await page.getByRole('menuitem', { name, exact: true }).click();
    const dialog = DETAIL_DIALOGS[name];
    const surface = dialog === undefined ? page.getByRole('dialog', { name }) : page.getByRole('alertdialog', { name: dialog });
    await expect(surface).toBeVisible();

    return surface;
}

/** Opens a content item from the Kalender list by its title, the way people reach it. */
export async function openContent(page: Page, title: string, archived = false): Promise<void> {
    await page.goto(`/content/calendar?view=list${archived ? '&status=ARCHIVED' : ''}&search=${encodeURIComponent(title.slice(0, 12))}`);
    // The list table, not the Perlu diposting reminders above it, which also link Ready posts once they are due.
    await page.getByRole('table').getByRole('link', { name: title }).click();
    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
}
