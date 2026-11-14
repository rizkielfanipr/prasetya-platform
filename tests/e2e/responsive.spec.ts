import { expect, type Page } from '@playwright/test';
import { login, openContent, test } from './support';

async function expectNoHorizontalOverflow(page: Page): Promise<void> {
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
}

const pages = [
    '/dashboard',
    '/transactions',
    '/transactions?view=attention',
    '/transactions?view=events',
    '/users',
    '/settings',
    '/settings/activity',
    '/content/calendar',
    '/content/calendar?view=list',
];

for (const [name, width, height] of [
    ['small mobile', 320, 568],
    ['mobile', 360, 740],
    ['tablet', 768, 1024],
    ['laptop', 1280, 900],
    ['desktop', 1440, 900],
] as const) {
    test(`no page overflows horizontally on ${name}`, async ({ page }) => {
        await page.setViewportSize({ width, height });
        await login(page);

        for (const path of pages) {
            await page.goto(path);
            await page.waitForLoadState('networkidle');
            await expectNoHorizontalOverflow(page);
        }

        // One seeded content item per status workspace (ADR-0013).
        for (const [title, archived] of [
            ['Cerita klien: toko roti go online', false],
            ['3 tanda website Anda perlu diperbarui', false],
            ['Kenapa website lambat merugikan bisnis', false],
            ['Promo lama yang dibatalkan', true],
        ] as const) {
            await openContent(page, title, archived);
            await page.waitForLoadState('networkidle');
            await expectNoHorizontalOverflow(page);
        }
    });
}

test('login card stays usable at 320px', async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 640 });
    await page.goto('/login');
    await expectNoHorizontalOverflow(page);
    await expect(page.getByRole('button', { name: 'Masuk' })).toBeVisible();
});

test('mobile navigation is an accessible keyboard-operable drawer', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await login(page);

    const openButton = page.getByRole('button', { name: 'Buka menu navigasi' });
    await expect(page.getByRole('navigation', { name: 'Navigasi utama' })).toHaveCount(0);

    await openButton.focus();
    await page.keyboard.press('Enter');
    const drawer = page.getByRole('dialog');
    await expect(drawer.getByRole('link', { name: 'Transaksi' })).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(drawer).toBeHidden();
    await expect(openButton).toBeFocused();

    await openButton.click();
    await drawer.getByRole('link', { name: 'Transaksi' }).click();
    await expect(page).toHaveURL(/\/transactions/);
    await expect(drawer).toBeHidden();
});
