import { expect, type Page } from '@playwright/test';
import { crc32, deflateSync } from 'node:zlib';
import { expectNoAxeViolations, login, logout, test, USERS } from './support';

/** A valid single-colour PNG, built here so the test needs no image fixture on disk. */
function png(width: number, height: number): Buffer {
    const chunk = (type: string, data: Buffer) => {
        const body = Buffer.concat([Buffer.from(type, 'ascii'), data]);
        const length = Buffer.alloc(4);
        length.writeUInt32BE(data.length);
        const crc = Buffer.alloc(4);
        crc.writeUInt32BE(crc32(body));
        return Buffer.concat([length, body, crc]);
    };
    const header = Buffer.alloc(13);
    header.writeUInt32BE(width, 0);
    header.writeUInt32BE(height, 4);
    header.set([8, 2, 0, 0, 0], 8);
    const row = Buffer.concat([Buffer.from([0]), Buffer.alloc(width * 3, 0x80)]);
    const raw = Buffer.concat(Array.from({ length: height }, () => row));

    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        chunk('IHDR', header),
        chunk('IDAT', deflateSync(raw)),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}

async function settled(page: Page): Promise<void> {
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => Promise.allSettled(document.getAnimations().map((animation) => animation.finished)));
}

test('one post goes from draft to published with two people, and the submitter cannot approve it', async ({ page }) => {
    const title = `Posting uji ${Date.now().toString(36)}`;
    await login(page, USERS.admin);

    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Content Studio' }).click();
    await expect(page).toHaveURL(/\/content\/calendar/);
    await page.getByRole('button', { name: 'Buat konten' }).click();
    const create = page.getByRole('alertdialog', { name: 'Buat konten' });
    await create.getByLabel('Judul kerja').fill(title);
    await create.getByRole('button', { name: 'Buat draft' }).click();
    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();

    // Submitting needs the required content first; the reason is shown beside the disabled button.
    await expect(page.getByRole('button', { name: 'Ajukan untuk ditinjau' })).toBeDisabled();
    await expect(page.locator('#reason-submit')).toHaveText('Lengkapi brief, caption, alt text dan gambar sebelum mengajukan.');

    await page.getByLabel('Brief *').fill('Satu gambar dengan judul besar.');
    await page.getByLabel('Caption *').fill('Website yang rapi membantu pelanggan percaya.');
    await page.getByLabel('Hashtag').fill('#websiteumkm #bisnisonline');
    await page.getByLabel('Alt text *').fill('Kartu abu-abu bertuliskan judul posting.');
    await page.getByLabel('Unggah gambar').setInputFiles({ name: 'post.png', mimeType: 'image/png', buffer: png(1080, 1350) });
    await page.getByRole('button', { name: 'Simpan konten' }).click();
    await expect(page.getByText('Revisi 1 disimpan.')).toBeVisible();
    await expect(page.getByRole('img', { name: 'Kartu abu-abu bertuliskan judul posting.' })).toBeVisible();

    await page.getByRole('button', { name: 'Ajukan untuk ditinjau' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Ajukan' }).click();
    await expect(page.getByTestId('review-summary')).toContainText('menunggu tinjauan');
    await expect(page.getByRole('button', { name: 'Setujui' })).toBeDisabled();
    await expect(page.locator('#reason-approve')).toHaveText('Revisi ini Anda ajukan sendiri. Pengguna lain harus menyetujuinya.');
    const itemUrl = page.url();

    await logout(page);
    await login(page, USERS.colleague);
    await page.goto(itemUrl);
    await page.getByRole('button', { name: 'Setujui' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Setujui' }).click();
    await expect(page.getByTestId('review-summary')).toContainText('Revisi 1 disetujui');

    // Posting package: copy, download, then record the human posting.
    await expect(page.getByRole('link', { name: 'Unduh gambar' })).toHaveAttribute('href', /\/content\/assets\/.+download=1/);
    await page.getByRole('button', { name: 'Tandai sudah dipublikasikan' }).click();
    const publish = page.getByRole('alertdialog', { name: 'Tandai sudah dipublikasikan?' });
    await expect(publish.getByLabel('Waktu diposting (WIB) *')).not.toHaveValue('');
    await publish.getByLabel('URL posting Instagram').fill('https://www.instagram.com/p/UJI123/');
    await publish.getByRole('button', { name: 'Tandai dipublikasikan' }).click();
    await expect(page.getByTestId('publication')).toContainText('Dipublikasikan');
    await expect(page.getByRole('main').getByText('Dipublikasikan', { exact: true }).first()).toBeVisible();
    await expect(page.getByRole('list', { name: 'Riwayat audit' })).toContainText('Ditandai dipublikasikan');
});

test('a reviewer asks for a revision and the writer resubmits a new revision', async ({ page }) => {
    await login(page, USERS.colleague);
    await page.goto('/content/items?search=Checklist');
    await page.getByRole('link', { name: 'Checklist sebelum meluncurkan website' }).click();

    await expect(page.getByTestId('review-summary')).toContainText('meminta revisi');
    await page.getByLabel('Caption *').fill('Checklist singkat sebelum website diluncurkan.');
    await page.getByRole('button', { name: 'Simpan konten' }).click();
    await expect(page.getByText('Revisi 2 disimpan.')).toBeVisible();
    await expect(page.getByTestId('revision-2')).toContainText('Belum diajukan');
    await expect(page.getByTestId('revision-diff')).toContainText('Checklist singkat sebelum website diluncurkan.');

    await page.getByRole('button', { name: 'Ajukan untuk ditinjau' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Ajukan' }).click();
    await expect(page.getByTestId('review-summary')).toContainText('Revisi 2 diajukan');
});

test('content pages have no accessibility violations', async ({ page }) => {
    await login(page, USERS.admin);
    for (const path of ['/content/calendar', '/content/items']) {
        await page.goto(path);
        await settled(page);
        await expectNoAxeViolations(page);
    }
    await page.goto('/content/items?search=Domain');
    await page.getByRole('link', { name: 'Domain, hosting, dan SSL dalam satu menit' }).click();
    await settled(page);
    await expectNoAxeViolations(page);

    await page.goto('/content/items?search=3%20tanda');
    await page.getByRole('link', { name: '3 tanda website Anda perlu diperbarui' }).click();
    await settled(page);
    await expectNoAxeViolations(page);
    await page.getByRole('button', { name: 'Tandai sudah dipublikasikan' }).click();
    await settled(page);
    await expectNoAxeViolations(page);
});

test('the calendar moves between weeks and opens an item', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/calendar');
    const days = page.getByRole('list', { name: 'Konten minggu ini per hari' });
    await expect(days.getByRole('link', { name: /Kenapa website lambat merugikan bisnis/ })).toBeVisible();

    await page.getByRole('link', { name: 'Minggu berikutnya' }).click();
    await expect(page).toHaveURL(/week=/);
    await expect(days.getByRole('link', { name: /Kenapa website lambat/ })).toHaveCount(0);
    await page.getByRole('link', { name: 'Minggu ini' }).click();
    await days.getByRole('link', { name: /Kenapa website lambat merugikan bisnis/ }).click();
    await expect(page.getByTestId('publication')).toBeVisible();
});
