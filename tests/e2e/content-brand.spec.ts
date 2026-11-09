import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test, USERS } from './support';

test('one free-text Brand Knowledge document is versioned and chunked', async ({ page }) => {
    await login(page, USERS.admin);

    await page.goto('/content/brands');
    await expect(page.getByRole('link', { name: 'Brand Knowledge' })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByText('Panduan mengisi Brand Knowledge')).toBeVisible();
    await expect(page.getByText('AI mencari bagian yang relevan')).toBeVisible();
    const knowledge = page.getByLabel('Brand Knowledge *');
    await knowledge.fill('Tentang brand\nKami membantu pemilik UMKM membuat website yang mudah dikelola.\n\nHarga\nPaket website mulai Rp3.500.000.');
    await page.getByRole('button', { name: 'Simpan dan proses knowledge' }).click();
    await expect(page.getByText('Brand Knowledge disimpan sebagai versi 1.')).toBeVisible();
    await expect(page.getByText(/versi 1 · 2 bagian pencarian/)).toBeVisible();
    await page.reload();
    await expect(page.getByLabel('Brand Knowledge *')).toHaveValue(/Paket website mulai Rp3\.500\.000\./);
});

test('AI interview proposes editable knowledge without saving a new version', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/brands');
    await page.route('**/knowledge/assist', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 1200));
        await route.continue();
    });
    await page.getByRole('button', { name: 'Bantuan AI' }).click();
    await expect(page.getByRole('heading', { name: 'Susun Brand Knowledge dengan AI' })).toBeVisible();
    await page.getByLabel('Apa yang dilakukan brand ini?').fill('Kami mendampingi UMKM membangun website yang mudah dikelola.');
    await page.getByLabel('Bagaimana gaya komunikasi brand?').fill('Ramah, profesional, dan tidak memakai jargon teknis.');
    await page.getByRole('button', { name: 'Olah jawaban dengan AI' }).click();

    await expect(page.getByRole('heading', { name: 'AI sedang mengolah jawaban' })).toBeVisible();
    await expect(page.getByText(/Waktu berjalan:/)).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Susun Brand Knowledge dengan AI' })).toBeVisible();
    await expect(page.getByText('Usulan AI sudah masuk ke editor. Periksa dan simpan jika sudah sesuai.')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Susun Brand Knowledge dengan AI' })).not.toBeVisible();
    await expect(page.getByLabel('Brand Knowledge *')).toHaveValue(/Kami mendampingi UMKM membangun website/);
    await expect(page.getByText(/versi 1 · 2 bagian pencarian/)).toBeVisible();
});

test('the legacy campaign tab opens campaign planning on the calendar', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/brands');
    await page.waitForURL(/content\/brands\/.+/);
    await page.goto(`${page.url()}?tab=campaigns`);
    await expect(page).toHaveURL(/content\/calendar\?.*manage_campaigns=1/);
    await expect(page.getByRole('heading', { name: 'Campaign' })).toBeVisible();
});

test('Brand Knowledge and campaign planning have no accessibility violations', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/brands');
    await expectNoAxeViolations(page);
    await page.goto('/content/calendar?manage_campaigns=1');
    await expect(page.getByRole('heading', { name: 'Campaign' })).toBeVisible();
    await expectNoAxeViolations(page);
});
