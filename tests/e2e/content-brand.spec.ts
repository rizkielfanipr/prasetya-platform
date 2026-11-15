import { expect } from '@playwright/test';
import { expectNoAxeViolations, login, test, USERS } from './support';

test('one Brand document is versioned and chunked, and its Aturan Posting section sets the rules', async ({ page }) => {
    await login(page, USERS.admin);

    await page.goto('/content/brands');
    await expect(page.getByRole('link', { name: 'Brand' })).toHaveAttribute('aria-current', 'page');
    const rules = page.getByTestId('posting-rules');
    await expect(rules).toContainText('Aturan versi 1 berlaku, tetapi belum tertulis di dokumen ini.');
    const knowledge = page.getByLabel('Dokumen brand *');
    await knowledge.fill('Tentang brand\nKami membantu pemilik UMKM membuat website yang mudah dikelola.\n\nHarga\nPaket website mulai Rp3.500.000.');
    // The rules in force move into the document unchanged.
    await rules.getByTestId('insert-rules').click();
    await expect(knowledge).toHaveValue(/## Aturan Posting\n\n\[BATAS\]\nCaption minimal:\nCaption maksimal: 600/);
    await expect(rules).toContainText('Aturan ada di bagian ## Aturan Posting dokumen ini');
    await page.getByRole('button', { name: 'Simpan dokumen' }).click();
    await expect(page.getByText('Brand Knowledge disimpan sebagai versi 1.')).toBeVisible();
    await page.reload();
    await expect(page.getByLabel('Dokumen brand *')).toHaveValue(/Paket website mulai Rp3\.500\.000\./);
    await expect(page.getByTestId('posting-rules')).toContainText('Yang berlaku sekarang: versi 1');
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
    await expect(page.getByLabel('Dokumen brand *')).toHaveValue(/Kami mendampingi UMKM membangun website/);
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

test('a campaign needs only a name, a period, and an objective, and shows whether it runs', async ({ page }) => {
    const name = `Campaign uji ${Date.now().toString(36)}`;
    await login(page, USERS.admin);
    await page.goto('/content/calendar?manage_campaigns=1');

    await page.getByRole('button', { name: 'Tambah campaign' }).first().click();
    const dialog = page.getByRole('alertdialog', { name: 'Tambah campaign' });
    await expect(dialog.getByLabel('Prioritas')).toHaveCount(0);
    await expect(dialog.getByRole('checkbox')).toHaveCount(0);
    await expectNoAxeViolations(page);
    await dialog.getByLabel('Nama *').fill(name);
    const today = new Date().toLocaleDateString('en-CA', { timeZone: 'Asia/Jakarta' });
    await dialog.getByLabel('Tanggal mulai *').fill(today);
    await dialog.getByLabel('Tanggal selesai *').fill(today);
    await dialog.getByLabel('Tujuan').fill('Uji campaign minimal');
    await dialog.getByRole('button', { name: 'Simpan campaign' }).click();

    const row = page.getByTestId(`campaign-row-${name}`);
    await expect(row).toContainText('Berjalan');

    // It is offered for a new post planned today.
    await page.getByRole('button', { name: 'Tambah konten' }).click();
    const create = page.getByRole('alertdialog', { name: 'Tambah konten' });
    await create.getByLabel('Jadwal posting (WIB) *').fill(`${today}T10:00`);
    await expect(create.getByLabel('Campaign')).toContainText(name);
});
