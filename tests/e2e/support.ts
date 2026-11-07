import AxeBuilder from '@axe-core/playwright';
import { type ConsoleMessage, expect, type Page, test as base } from '@playwright/test';

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
    const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    expect(results.violations.map((v) => ({ id: v.id, nodes: v.nodes.map((n) => n.target.join(' ')).slice(0, 5) }))).toEqual([]);
}
