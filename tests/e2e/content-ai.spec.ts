import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test, USERS } from './support';

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
    await expect(page.getByTestId('ai-budget')).toContainText('dari $5.00');
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
