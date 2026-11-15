import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, openContent, test, USERS } from './support';

// ADR-0017: Referensi replaces web research. Links are added by hand, checked by the server, and removable on a Draft.
test('add a reference to a Draft, refuse an internal address, and remove it again', async ({ page }) => {
    await login(page, USERS.admin);
    await openContent(page, 'Domain, hosting, dan SSL dalam satu menit');
    const references = page.getByTestId('content-references');
    await expect(references).toContainText('Belum ada referensi.');

    await references.getByTestId('add-reference').click();
    await references.getByLabel('URL *').fill('http://127.0.0.1/admin');
    await references.getByLabel('Judul').fill('Internal');
    await references.getByRole('button', { name: 'Tambah referensi' }).click();
    await expect(references.getByText('Alamat jaringan privat atau lokal tidak boleh dipakai.')).toBeVisible();

    await references.getByLabel('URL *').fill('https://contoh-artikel.id/tips-domain?utm_source=ig');
    await references.getByLabel('Judul').fill('Tips memilih domain');
    await references.getByLabel('Catatan').fill('Untuk poin pertama');
    await references.getByRole('button', { name: 'Tambah referensi' }).click();
    const link = references.getByRole('link', { name: /Tips memilih domain/ });
    await expect(link).toHaveAttribute('href', 'https://contoh-artikel.id/tips-domain');
    await expect(references).toContainText('Untuk poin pertama');
    await expectNoAxeViolations(page);

    await references.getByRole('button', { name: 'Hapus referensi Tips memilih domain' }).click();
    await expect(references).toContainText('Belum ada referensi.');
});
