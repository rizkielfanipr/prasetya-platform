import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test, USERS } from './support';

test('Siap posting lists approved posts waiting to be posted by hand', async ({ page }) => {
    await login(page, USERS.admin);
    await page
        .getByRole('navigation', { name: 'Navigasi utama' })
        .getByRole('link', { name: /Content Studio/ })
        .click();
    await page
        .getByRole('navigation', { name: 'Bagian Content Studio' })
        .getByRole('link', { name: /Siap posting/ })
        .click();

    await expect(page).toHaveURL(/\/content\/to-post/);
    await expect(page.getByRole('main')).toContainText('3 tanda website Anda perlu diperbarui');
    await expect(page.getByRole('main')).not.toContainText('Kenapa website lambat merugikan bisnis'); // Already published.
    await expectNoAxeViolations(page);

    await page.getByRole('link', { name: '3 tanda website Anda perlu diperbarui' }).click();
    await expect(page.getByRole('heading', { name: 'Paket posting' })).toBeVisible();
    await expect(page.getByText('Centang semua langkah checklist dulu.')).toBeVisible();
});
