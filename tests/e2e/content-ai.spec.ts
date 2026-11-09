import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test, USERS } from './support';

test('AI drafts copy as a new Draft revision that the creator still checks', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/calendar?view=list&search=Tanya');
    await page.getByRole('link', { name: 'Tanya jawab: berapa lama membuat website?' }).click();

    const panel = page.getByTestId('ai-panel');
    await panel.getByRole('button', { name: 'Buat draft dengan AI' }).click();
    await expect(page.getByText('AI sedang membuat draft. Hasilnya menjadi revisi Draft baru untuk Anda periksa.')).toBeVisible();

    await expect(panel.getByTestId('ai-run-SUCCEEDED').first()).toContainText('revisi 2');
    await expect(page.getByTestId('ai-draft-note')).toContainText('Draft ini ditulis AI (fake-copy-1)');
    await expect(page.getByLabel('Caption *')).toHaveValue(/\[Draft AI contoh\] Tanya jawab: berapa lama membuat website\?/);
    await expect(page.getByTestId('revision-1')).toContainText('Masih draft');
    await expect(page.getByTestId('revision-2')).toContainText('dengan AI (fake-copy-1)');
    await expect(page.getByTestId('status-summary')).toContainText('tandai siap posting');
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
    await page.goto('/content/calendar?view=list&search=Domain');
    await page.getByRole('link', { name: 'Domain, hosting, dan SSL dalam satu menit' }).click();
    await page.waitForLoadState('networkidle');
    await expect(page.getByTestId('ai-panel')).toBeVisible();
    await expectNoAxeViolations(page);
});

test('the creator asks AI to redraft only the chosen parts, keeping every version', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/calendar?view=list&search=Cerita');
    await page.getByRole('link', { name: 'Cerita klien: toko roti go online' }).click();

    const panel = page.getByTestId('ai-panel');
    await expect(panel.getByTestId('ai-follows-review')).toHaveCount(0);
    for (const field of ['Hashtag', 'Alt text', 'Arahan visual']) {
        await panel.getByRole('checkbox', { name: field }).uncheck();
    }
    await expect(panel.getByRole('checkbox', { name: 'Caption' })).toBeChecked();
    await panel.getByLabel('Instruksi tambahan (opsional)').fill('lebih hangat');
    await panel.getByRole('button', { name: 'Buat draft dengan AI' }).click();

    const run = panel.getByTestId('ai-run-SUCCEEDED').first();
    await expect(run).toContainText('Instruksi: “lebih hangat”');
    await expect(page.getByTestId('revision-2')).toContainText('dengan AI');
    await expect(page.getByTestId('status-summary')).toContainText('tandai siap posting');

    await run.getByRole('button', { name: /kurang berguna$/ }).click();
    const why = page.getByRole('alertdialog', { name: 'Kenapa draft ini kurang berguna?' });
    await why.getByLabel('Alasan *').fill('Masih terlalu umum');
    await why.getByRole('button', { name: 'Simpan penilaian' }).click();
    await expect(panel.getByTestId('ai-run-SUCCEEDED').first()).toContainText('Dinilai kurang berguna: Masih terlalu umum');
});
