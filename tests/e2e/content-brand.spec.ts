import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test, USERS } from './support';

test('a brand rule written in Brand & Template blocks content that breaks it', async ({ page }) => {
    const term = `kataterlarang${Date.now().toString(36)}`;
    await login(page, USERS.admin);

    await page.goto('/content/brands');
    await expect(page.getByRole('link', { name: 'Brand & Template' })).toHaveAttribute('aria-current', 'page');
    await page.getByRole('button', { name: 'Ubah aturan' }).click();
    const form = page.getByRole('form', { name: 'Ubah aturan brand' });
    const forbidden = form.getByLabel('Kata terlarang');
    await forbidden.fill(`${await forbidden.inputValue()}\n${term}`);
    await form.getByLabel('Alasan perubahan *').fill('Uji e2e');
    await form.getByRole('button', { name: 'Simpan sebagai versi baru' }).click();
    await expect(page.getByText(/Aturan brand disimpan sebagai versi \d+\./)).toBeVisible();
    await expect(page.getByTestId('brand-rules')).toContainText(term);

    await page.goto('/content/items?search=Domain');
    await page.getByRole('link', { name: 'Domain, hosting, dan SSL dalam satu menit' }).click();
    await page.getByLabel('Caption *').fill(`Website ${term} untuk usaha Anda. Tanyakan kebutuhan Anda lewat DM.`);
    await page.getByRole('button', { name: 'Simpan konten' }).click();
    await expect(page.getByText('Revisi 1 disimpan.')).toBeVisible();

    await expect(page.getByTestId('rule-findings')).toContainText(`kata terlarang "${term}"`);
    await expect(page.getByRole('button', { name: 'Ajukan untuk ditinjau' })).toBeDisabled();
    await expect(page.locator('#reason-submit')).toHaveText(/Lengkapi gambar sebelum mengajukan\.|Perbaiki 1 pelanggaran aturan brand sebelum mengajukan\./);
});

test('pillars keep their targets within 100% and the calendar shows the week against them', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/brands');
    await page.getByRole('navigation', { name: 'Bagian brand' }).getByRole('link', { name: 'Pilar' }).click();
    await expect(page.getByText('Total target pilar aktif: 100% dari 100%.')).toBeVisible();

    await page.getByRole('button', { name: 'Tambah pilar' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Tambah pilar' });
    await dialog.getByLabel('Nama').fill(`Tips ${Date.now().toString(36)}`);
    await dialog.getByLabel('Target (%)').fill('10');
    await dialog.getByRole('button', { name: 'Simpan pilar' }).click();
    await expect(page.getByText('Total target pilar aktif lebih dari 100%. Sisa yang tersedia: 0%.')).toBeVisible();

    await page.goto('/content/calendar');
    await expect(page.getByTestId('composition-Edukasi')).toContainText('target 50%');
});

test('Brand & Template pages have no accessibility violations', async ({ page }) => {
    await login(page, USERS.admin);
    for (const tab of ['rules', 'pillars', 'campaigns', 'facts', 'activity']) {
        await page.goto('/content/brands');
        await page.waitForURL(/content\/brands\/.+/);
        await page.goto(`${page.url().split('?')[0]}?tab=${tab}`);
        await page.waitForLoadState('networkidle');
        await expectNoAxeViolations(page);
    }
    await page.goto('/content/brands');
    await page.getByRole('button', { name: 'Ubah aturan' }).click();
    await expectNoAxeViolations(page);
});
