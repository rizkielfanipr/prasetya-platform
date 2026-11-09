import { expect, type Page } from '@playwright/test';
import { crc32, deflateSync } from 'node:zlib';
import { expectNoAxeViolations, login, test, USERS } from './support';

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

test('one person takes a post from draft to Ready to published, with no approval step', async ({ page }) => {
    const title = `Posting uji ${Date.now().toString(36)}`;
    await login(page, USERS.admin);

    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Content Studio' }).click();
    await expect(page).toHaveURL(/\/content\/calendar/);
    await page.getByRole('button', { name: 'Tambah konten' }).click();
    const create = page.getByRole('alertdialog', { name: 'Tambah konten' });
    await create.getByLabel('Judul kerja').fill(title);
    await create.getByRole('button', { name: 'Buat draft' }).click();
    await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();

    // Ready needs the required content first; the reason is shown beside the disabled button.
    await expect(page.getByRole('button', { name: 'Tandai siap posting' })).toBeDisabled();
    await expect(page.locator('#reason-markReady')).toHaveText('Lengkapi caption, alt text dan gambar sebelum menandai siap posting.');
    await expect(page.getByRole('button', { name: /Setujui|Ajukan|Minta revisi|Tolak/ })).toHaveCount(0);

    await page.getByLabel('Caption *').fill('Website yang rapi membantu pelanggan percaya.');
    await page.getByRole('textbox', { name: 'Hashtag', exact: true }).fill('#websiteumkm #bisnisonline');
    await page.getByLabel('Alt text *').fill('Kartu abu-abu bertuliskan judul posting.');
    await page.getByLabel('Unggah gambar').setInputFiles({ name: 'post.png', mimeType: 'image/png', buffer: png(1080, 1350) });
    await page.getByRole('button', { name: 'Simpan konten' }).click();
    await expect(page.getByText('Revisi 1 disimpan.')).toBeVisible();
    await expect(page.getByRole('img', { name: 'Kartu abu-abu bertuliskan judul posting.' })).toBeVisible();

    await page.getByRole('button', { name: 'Tandai siap posting' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Tandai siap posting' }).click();
    await expect(page.getByTestId('status-summary')).toContainText('Revisi 1 siap diposting');

    // Posting package: copy, download, then record the human posting, all by the same person.
    await expect(page.getByRole('link', { name: 'Unduh gambar' })).toHaveAttribute('href', /\/content\/assets\/.+download=1/);
    await expect(page.getByRole('button', { name: 'Tandai sudah diposting' })).toBeDisabled();
    for (const step of [
        'Gambar dari paket ini sudah diunggah ke Instagram',
        'Caption dan hashtag ditempel apa adanya',
        'Alt text diisi di Pengaturan lanjutan Instagram',
        'Diposting dari akun brand yang benar',
    ]) {
        await page.getByRole('checkbox', { name: step }).check();
    }
    await page.getByRole('button', { name: 'Tandai sudah diposting' }).click();
    const publish = page.getByRole('alertdialog', { name: 'Tandai sudah diposting?' });
    await expect(publish.getByLabel('Waktu diposting (WIB) *')).not.toHaveValue('');
    await publish.getByLabel('URL posting Instagram').fill('https://www.instagram.com/p/UJI123/');
    await publish.getByRole('button', { name: 'Tandai sudah diposting' }).click();
    await expect(page.getByTestId('publication')).toContainText('Diposting');
    await expect(page.getByRole('main').getByText('Sudah diposting', { exact: true }).first()).toBeVisible();
    await expect(page.getByRole('list', { name: 'Riwayat audit' })).toContainText('Ditandai siap posting');
    await expect(page.getByRole('list', { name: 'Riwayat audit' })).toContainText('Ditandai sudah diposting');
});

test('editing Ready content makes a new revision and returns it to Draft', async ({ page }) => {
    await login(page, USERS.colleague);
    await page.goto('/content/calendar?view=list&search=3%20tanda');
    await page.getByRole('link', { name: '3 tanda website Anda perlu diperbarui' }).click();

    await expect(page.getByTestId('status-summary')).toContainText('siap diposting');
    await page.getByLabel('Caption *').fill('Tiga tanda singkat website Anda perlu diperbarui.');
    await page.getByRole('button', { name: 'Simpan konten' }).click();
    await expect(page.getByText('Revisi 2 disimpan dan konten kembali ke Draft.')).toBeVisible();
    await expect(page.getByTestId('revision-2')).toContainText('Masih draft');
    await expect(page.getByTestId('revision-diff')).toContainText('Tiga tanda singkat website Anda perlu diperbarui.');

    await page.getByRole('button', { name: 'Tandai siap posting' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Tandai siap posting' }).click();
    await expect(page.getByTestId('status-summary')).toContainText('Revisi 2 siap diposting');
});

test('a draft can be archived with an optional reason', async ({ page }) => {
    await login(page, USERS.admin);
    await page.goto('/content/calendar?view=list&search=Checklist');
    await page.getByRole('link', { name: 'Checklist sebelum meluncurkan website' }).click();

    await page.getByRole('button', { name: 'Arsipkan' }).click();
    const dialog = page.getByRole('alertdialog', { name: /Arsipkan/ });
    await dialog.getByLabel('Alasan (opsional)').fill('Digabung ke posting lain');
    await dialog.getByRole('button', { name: 'Arsipkan' }).click();
    await expect(page.getByTestId('status-summary')).toContainText('diarsipkan');
    await expect(page.getByRole('button', { name: 'Arsipkan' })).toHaveCount(0);
});

test('content pages have no accessibility violations', async ({ page }) => {
    await login(page, USERS.admin);
    for (const path of ['/content/calendar', '/content/calendar?view=list', '/content/calendar?view=list&status=ARCHIVED']) {
        await page.goto(path);
        await settled(page);
        await expectNoAxeViolations(page);
    }
    await page.goto('/content/calendar?view=list&search=Domain');
    await page.getByRole('link', { name: 'Domain, hosting, dan SSL dalam satu menit' }).click();
    await settled(page);
    await expectNoAxeViolations(page);

    await page.goto('/content/calendar?view=list&search=3%20tanda');
    await page.getByRole('link', { name: '3 tanda website Anda perlu diperbarui' }).click();
    await settled(page);
    await expectNoAxeViolations(page);
    for (const step of [
        'Gambar dari paket ini sudah diunggah ke Instagram',
        'Caption dan hashtag ditempel apa adanya',
        'Alt text diisi di Pengaturan lanjutan Instagram',
        'Diposting dari akun brand yang benar',
    ]) {
        await page.getByRole('checkbox', { name: step }).check();
    }
    await page.getByRole('button', { name: 'Tandai sudah diposting' }).click();
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
