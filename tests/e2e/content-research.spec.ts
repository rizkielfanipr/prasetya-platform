import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, openContent, openDetailSurface, test, USERS } from './support';

test('research a post on the web, cite a result, and add a manual source', async ({ page }) => {
    await login(page, USERS.admin);
    await openContent(page, 'Domain, hosting, dan SSL dalam satu menit');
    const sheet = await openDetailSurface(page, 'Riset & sumber');
    const panel = sheet.getByTestId('research-panel');
    await expect(panel).toContainText('knowledge berlaku');

    await panel.getByLabel('Kata kunci riset').fill('domain hosting umkm');
    await panel.getByRole('button', { name: 'Cari di web' }).click();
    const run = panel.getByTestId('research-run-SUCCEEDED').first();
    await expect(run).toContainText('1 hasil dibuang karena alamat atau domainnya tidak aman.');
    await expect(run).toContainText('Resmi');
    await expect(run).toContainText('Konten pengguna');

    await run.getByRole('button', { name: /^Pakai sumber Data resmi/ }).click();
    await expect(panel.getByTestId('cited-sources')).toContainText('Data resmi: domain hosting umkm');
    await expect(run.getByRole('button', { name: /sudah dipakai$/ })).toBeDisabled();

    await panel.getByRole('button', { name: 'Tambah sumber manual' }).click();
    const dialog = page.getByRole('alertdialog', { name: 'Tambah sumber manual' });
    await dialog.getByLabel('URL *').fill('http://127.0.0.1/admin');
    await dialog.getByLabel('Judul').fill('Internal');
    await dialog.getByRole('button', { name: 'Tambah sumber' }).click();
    await expect(dialog.getByText('Alamat jaringan privat atau lokal tidak boleh dipakai.')).toBeVisible();
    await dialog.getByLabel('URL *').fill('https://contoh-artikel.id/tips-domain?utm_source=ig');
    await dialog.getByLabel('Judul').fill('Tips memilih domain');
    await dialog.getByLabel('Kenapa relevan').fill('Untuk poin pertama');
    await dialog.getByRole('button', { name: 'Tambah sumber' }).click();
    await expect(panel.getByTestId('cited-sources')).toContainText('Catatan tim: Untuk poin pertama');
    await expect(panel.getByTestId('cited-sources').getByRole('link', { name: /Tips memilih domain/ })).toHaveAttribute(
        'href',
        'https://contoh-artikel.id/tips-domain',
    );
    await expectNoAxeViolations(page);
});
