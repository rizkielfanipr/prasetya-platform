import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test, USERS } from './support';

test('the Kalender is the workspace: four sections, a status filter, a list view, and old links still land', async ({ page }) => {
    await login(page, USERS.admin);
    await page
        .getByRole('navigation', { name: 'Navigasi utama' })
        .getByRole('link', { name: /Content Studio/ })
        .click();
    await expect(page).toHaveURL(/\/content\/calendar/);
    await expect(page.getByRole('navigation', { name: 'Bagian Content Studio' }).getByRole('link')).toHaveText([
        /Kalender/,
        'Insight',
        'Brand Knowledge',
        'Pengaturan AI',
    ]);

    const days = page.getByRole('list', { name: 'Konten minggu ini per hari' });
    const status = page.getByRole('group', { name: 'Status konten' });
    await expect(status.getByRole('button', { name: 'Semua' })).toHaveAttribute('aria-pressed', 'true');
    await expect(days).not.toContainText('Promo lama yang dibatalkan'); // Archived: only under Filter lainnya.

    await status.getByRole('button', { name: 'Siap posting' }).click();
    await expect(page).toHaveURL(/status=READY/);
    await expect(status.getByRole('button', { name: 'Siap posting' })).toHaveAttribute('aria-pressed', 'true');
    await expect(days).toContainText('3 tanda website Anda perlu diperbarui');
    await expect(days).not.toContainText('Kenapa website lambat merugikan bisnis'); // Already posted.

    await page.getByRole('navigation', { name: 'Tampilan kalender' }).getByRole('link', { name: 'Daftar' }).click();
    await expect(page).toHaveURL(/view=list/);
    await expect(page).toHaveURL(/status=READY/);
    await expect(page.getByRole('table')).toContainText('3 tanda website Anda perlu diperbarui');
    await expectNoAxeViolations(page);

    await page.getByRole('button', { name: 'Filter lainnya' }).click();
    await page.getByRole('combobox', { name: /^Arsip/ }).click();
    await page.getByRole('option', { name: 'Hanya yang diarsipkan' }).click();
    await expect(page.getByRole('table')).toContainText('Promo lama yang dibatalkan');
    await expect(status.getByRole('button', { name: 'Semua' })).toHaveAttribute('aria-pressed', 'true');

    // The retired pages redirect with the same meaning.
    await page.goto('/content/to-post');
    await expect(page).toHaveURL(/\/content\/calendar\?(?=.*view=list)(?=.*status=READY)/);
    await expect(page.getByRole('table')).toContainText('3 tanda website Anda perlu diperbarui');
    await page.goto('/content/items?search=Cerita');
    await expect(page).toHaveURL(/\/content\/calendar\?(?=.*view=list)(?=.*search=Cerita)/);
    await expect(page.getByRole('searchbox', { name: 'Cari judul konten' })).toHaveValue('Cerita');

    await page.getByRole('link', { name: 'Cerita klien: toko roti go online' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Cerita klien: toko roti go online' })).toBeVisible();
    await page.goto('/content/calendar?view=list&status=READY');
    await page.getByRole('table').getByRole('link', { name: '3 tanda website Anda perlu diperbarui' }).click();
    await expect(page.getByRole('heading', { name: 'Paket posting' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Tandai sudah diposting' })).toBeEnabled();
});

test('the Kalender filters work from the keyboard', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/calendar');

    const draft = page.getByRole('group', { name: 'Status konten' }).getByRole('button', { name: 'Draft' });
    await draft.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/status=DRAFT/);
    await expect(draft).toHaveAttribute('aria-pressed', 'true');

    const more = page.getByRole('button', { name: 'Filter lainnya' });
    await more.focus();
    await page.keyboard.press('Space');
    await expect(more).toHaveAttribute('aria-expanded', 'true');
    await expect(page.getByRole('combobox', { name: /^Arsip/ })).toBeVisible();

    await page.getByRole('navigation', { name: 'Tampilan kalender' }).getByRole('link', { name: 'Daftar' }).focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(/view=list/);
    await expect(page).toHaveURL(/status=DRAFT/);
});
