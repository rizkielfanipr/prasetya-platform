import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, openContent, openDetailSurface, test, USERS } from './support';

test('record Instagram insight for a published post and see it summarised', async ({ page }) => {
    await login(page, USERS.admin);
    await openContent(page, 'Kenapa website lambat merugikan bisnis');
    await expect(page.getByLabel('Caption *')).toHaveCount(0); // Published: evidence and insight, no editor.

    const panel = page.getByTestId('insight-panel');
    await expect(panel).toContainText('Belum ada insight.');
    await panel.getByRole('button', { name: 'Catat insight' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Catat insight' });
    await dialog.getByLabel('Jangkauan *').fill('1200');
    await dialog.getByLabel('Suka').fill('80');
    await dialog.getByLabel('Simpan', { exact: true }).fill('20');
    await dialog.getByRole('button', { name: 'Simpan insight' }).click();
    await expect(panel).toContainText('8,3%');
    const activity = await openDetailSurface(page, 'Aktivitas');
    await expect(activity.getByRole('list', { name: 'Riwayat audit' })).toContainText('Insight dicatat');
    await page.keyboard.press('Escape');

    await page.goto('/content/calendar');
    await page.getByRole('navigation', { name: 'Bagian Content Studio' }).getByRole('link', { name: 'Insight' }).click();
    await expect(page.getByTestId('insight-totals')).toContainText('1 dari 1');
    await expect(page.getByTestId('insight-pillar')).toContainText('Edukasi');
    await expect(page.getByTestId('insight-top')).toContainText('Kenapa website lambat merugikan bisnis');
    await expectNoAxeViolations(page);
});
