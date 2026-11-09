import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, logout, test, USERS } from './support';

test('AI drafts copy as a new revision that a person still reviews', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/items?search=Tanya');
    await page.getByRole('link', { name: 'Tanya jawab: berapa lama membuat website?' }).click();

    const panel = page.getByTestId('ai-panel');
    await panel.getByRole('button', { name: 'Buat draft dengan AI' }).click();
    await expect(page.getByText('AI sedang membuat draft. Hasilnya menjadi revisi baru yang tetap perlu ditinjau.')).toBeVisible();

    await expect(panel.getByTestId('ai-run-SUCCEEDED').first()).toContainText('revisi 2');
    await expect(page.getByTestId('ai-draft-note')).toContainText('Draft ini ditulis AI (fake-copy-1)');
    await expect(page.getByLabel('Caption *')).toHaveValue(/\[Draft AI contoh\] Tanya jawab: berapa lama membuat website\?/);
    await expect(page.getByTestId('revision-1')).toContainText('Belum diajukan');
    await expect(page.getByTestId('revision-2')).toContainText('dengan AI (fake-copy-1)');
    await expect(page.getByTestId('review-summary')).toContainText('Lengkapi konten');
    await expect(page.getByRole('list', { name: 'Riwayat audit' })).toContainText('Draft AI diterapkan');
});

test('Pengaturan AI shows status, budget, and runs without any key', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/calendar');
    await page.getByRole('navigation', { name: 'Bagian Content Studio' }).getByRole('link', { name: 'Pengaturan AI' }).click();

    await expect(page.getByTestId('ai-status')).toContainText('AI siap membuat draft.');
    await expect(page.getByRole('main')).not.toContainText('Budget minggu ini');
    await expect(page.getByRole('main')).not.toContainText(/sk-[a-z0-9]/i); // A key value, never its variable name.
    await expectNoAxeViolations(page);
});

test('the AI panel is accessible', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/items?search=Domain');
    await page.getByRole('link', { name: 'Domain, hosting, dan SSL dalam satu menit' }).click();
    await page.waitForLoadState('networkidle');
    await expect(page.getByTestId('ai-panel')).toBeVisible();
    await expectNoAxeViolations(page);
});

test('a reviewer names the parts to change and AI redrafts only those, keeping every version', async ({ page }) => {
    await login(page, USERS.colleague);
    await page.goto('/content/items?search=Cerita');
    await page.getByRole('link', { name: 'Cerita klien: toko roti go online' }).click();
    await page.getByRole('button', { name: 'Minta revisi' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Minta revisi?' });
    await dialog.getByLabel('Caption').check();
    await dialog.getByLabel('Apa yang perlu diubah *').fill('Caption terlalu umum; sebut roti dan pesan lewat WhatsApp.');
    await dialog.getByRole('button', { name: 'Minta revisi' }).click();
    await expect(page.getByTestId('review-request')).toContainText('Caption');
    const itemUrl = page.url();
    await logout(page);

    await login(page, USERS.admin);
    await page.goto(itemUrl);
    const panel = page.getByTestId('ai-panel');
    await expect(panel.getByTestId('ai-follows-review')).toBeVisible();
    await expect(panel.getByRole('checkbox', { name: 'Caption' })).toBeChecked();
    await expect(panel.getByRole('checkbox', { name: 'Hashtag' })).not.toBeChecked();
    await panel.getByLabel('Instruksi tambahan (opsional)').fill('lebih hangat');
    await panel.getByRole('button', { name: 'Buat draft dengan AI' }).click();

    const run = panel.getByTestId('ai-run-SUCCEEDED').first();
    await expect(run).toContainText('Mengikuti catatan peninjau.');
    await expect(run).toContainText('Instruksi: “lebih hangat”');
    await expect(page.getByTestId('revision-1')).toContainText('Revisi diminta (Caption)');
    await expect(page.getByTestId('revision-2')).toContainText('dengan AI');

    await run.getByRole('button', { name: /kurang berguna$/ }).click();
    const why = page.getByRole('alertdialog', { name: 'Kenapa draft ini kurang berguna?' });
    await why.getByLabel('Alasan *').fill('Masih terlalu umum');
    await why.getByRole('button', { name: 'Simpan penilaian' }).click();
    await expect(panel.getByTestId('ai-run-SUCCEEDED').first()).toContainText('Dinilai kurang berguna: Masih terlalu umum');
});
