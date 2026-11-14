import { expect, type Page } from '@playwright/test';
import { crc32, deflateSync } from 'node:zlib';
import { expectNoAxeViolations, login, openContent, openDetailSurface, test, USERS } from './support';

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

test('one person takes an idea to a posted post, with AI and without opening any secondary surface', async ({ page }) => {
    const idea = `Posting uji ${Date.now().toString(36)}`;
    await login(page, USERS.admin);

    await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Content Studio' }).click();
    await expect(page).toHaveURL(/\/content\/calendar/);
    await page.getByRole('button', { name: 'Tambah konten' }).click();
    const create = page.getByRole('alertdialog', { name: 'Tambah konten' });
    await expect(create.getByLabel('Jadwal posting (WIB) *')).toHaveValue(/T10:00$/);
    await expect(create.getByLabel('Judul kerja')).toHaveCount(0);
    await create.getByRole('button', { name: 'Buat draft' }).click();
    await expect(create.getByText('Tulis ide atau topik konten.')).toBeVisible();
    await expectNoAxeViolations(page);
    await create.getByLabel('Ide atau topik *').fill(`${idea}\nUntuk pemilik UMKM yang baru online.`);
    await create.getByRole('button', { name: 'Buat draft' }).click();
    await expect(page.getByRole('heading', { level: 1, name: idea })).toBeVisible();
    await expect(page.getByLabel('Brief')).toHaveValue(`${idea}\nUntuk pemilik UMKM yang baru online.`);

    // Ready needs the required content first; the rail says what is missing, in words, and why Ready is disabled.
    await expect(page.getByTestId('mark-ready')).toBeDisabled();
    await expect(page.locator('#reason-markReady')).toHaveText('Lengkapi caption, alt text dan gambar sebelum menandai siap posting.');
    await expect(page.getByTestId('readiness-alt_text')).toHaveText('Alt text belum diisi');
    await expect(page.getByRole('button', { name: /Setujui|Ajukan|Minta revisi|Tolak/ })).toHaveCount(0);
    await expect(page.getByText('Pengaturan tambahan')).toHaveCount(0);

    await page.getByTestId('ai-panel').getByRole('button', { name: 'Buat dengan AI' }).click();
    await expect(page.getByLabel('Caption *')).toHaveValue(/\[Draft AI contoh\]/);
    await expect(page.getByRole('textbox', { name: 'Hashtag', exact: true })).toHaveValue(/#websiteumkm/);
    // Alt text sits with the image, so the AI's suggestion is visible and editable right there.
    await expect(page.getByLabel('Alt text *')).not.toHaveValue('');

    await page.getByLabel('Unggah gambar').setInputFiles({ name: 'post.png', mimeType: 'image/png', buffer: png(1080, 1350) });
    await expect(page.getByTestId('mark-ready')).toHaveCount(0);
    await expect(page.getByTestId('readiness-saved')).toHaveText('Ada perubahan yang belum disimpan');
    await page.getByTestId('save-content').click();
    await expect(page.getByText('Revisi 2 disimpan.')).toBeVisible();

    await page.getByTestId('mark-ready').click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Tandai siap posting' }).click();

    // Ready opens the posting package, not the editor. One click opens the posting dialog. No checklist.
    await expect(page.getByRole('heading', { name: 'Paket posting' })).toBeVisible();
    await expect(page.getByLabel('Caption *')).toHaveCount(0);
    await expect(page.getByTestId('posting-image')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Unduh gambar' })).toHaveAttribute('href', /\/content\/assets\/.+download=1/);
    await expect(page.getByRole('button', { name: 'Salin caption' })).toBeVisible();
    await expect(page.getByRole('checkbox')).toHaveCount(0);
    await page.getByRole('button', { name: 'Tandai sudah diposting' }).click();
    const publish = page.getByRole('alertdialog', { name: 'Tandai sudah diposting?' });
    await expect(publish.getByLabel('Waktu diposting (WIB) *')).not.toHaveValue('');
    await publish.getByLabel('URL posting Instagram').fill('https://www.instagram.com/p/UJI123/');
    await publish.getByRole('button', { name: 'Tandai sudah diposting' }).click();
    await expect(page.getByTestId('publication')).toContainText('Diposting');
    await expect(page.getByTestId('detail-status')).toContainText('Sudah diposting');
    await expect(page.getByRole('button', { name: 'Catat insight' })).toBeVisible();

    const activity = await openDetailSurface(page, 'Aktivitas');
    await expect(activity.getByRole('list', { name: 'Riwayat audit' })).toContainText('Draft AI diterapkan');
    await expect(activity.getByRole('list', { name: 'Riwayat audit' })).toContainText('Ditandai siap posting');
    await expect(activity.getByRole('list', { name: 'Riwayat audit' })).toContainText('Ditandai sudah diposting');
});

test('Detail kreatif keeps the optional details editable, and an error moves focus to its field', async ({ page }) => {
    await login(page, USERS.admin);
    await openContent(page, 'Checklist sebelum meluncurkan website');

    const creative = await openDetailSurface(page, 'Detail kreatif');
    await settled(page);
    await expectNoAxeViolations(page);
    await creative.getByLabel('Audiens').fill('Pemilik toko roti.');
    await creative.getByRole('button', { name: 'Simpan detail' }).click();
    await expect(page.getByText('Revisi 1 disimpan.')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(creative).toBeHidden();
    await expect(page.getByTestId('detail-menu')).toBeFocused();

    // An error on alt text, now beside the image: the summary names it and focus lands on the field.
    await page.getByLabel('Caption *').fill('Caption baru');
    await page.evaluate(() => {
        const alt = document.getElementById('content-alt_text') as HTMLTextAreaElement;
        alt.removeAttribute('maxlength');
        alt.value = 'x'.repeat(1001);
        alt.dispatchEvent(new Event('input'));
    });
    await page.getByTestId('save-content').click();
    await expect(page.getByLabel('Alt text *')).toBeFocused();
    await expect(page.getByTestId('error-summary')).toContainText('Alt text');
});

test('editing Ready content asks first, makes a new revision, and returns it to Draft', async ({ page }) => {
    await login(page, USERS.colleague);
    await openContent(page, '3 tanda website Anda perlu diperbarui');

    await expect(page.getByRole('heading', { name: 'Paket posting' })).toBeVisible();
    await page.getByTestId('edit-ready').click();
    const confirm = page.getByRole('alertdialog', { name: 'Edit konten yang sudah siap posting?' });
    await expect(confirm).toContainText('mengembalikan konten ke Draft');
    await confirm.getByRole('button', { name: 'Edit konten' }).click();
    await expect(page.getByTestId('ready-edit-note')).toBeVisible();
    await expect(page.getByTestId('detail-status')).toContainText('Siap posting');

    await page.getByLabel('Caption *').fill('Tiga tanda singkat website Anda perlu diperbarui.');
    await page.getByRole('button', { name: 'Simpan dan kembalikan ke Draft' }).click();
    await expect(page.getByText('Revisi 2 disimpan dan konten kembali ke Draft.')).toBeVisible();
    await expect(page.getByTestId('detail-status')).toContainText('Draft');

    const history = await openDetailSurface(page, 'Riwayat versi');
    await expect(history.getByTestId('revision-2')).toContainText('Masih draft');
    await expect(history.getByTestId('revision-diff')).toContainText('Tiga tanda singkat website Anda perlu diperbarui.');
    await page.keyboard.press('Escape');
    await expect(page.getByTestId('detail-menu')).toBeFocused();

    await page.getByTestId('mark-ready').click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Tandai siap posting' }).click();
    await expect(page.getByRole('heading', { name: 'Paket posting' })).toBeVisible();
});

test('a draft can be archived with an optional reason and becomes read-only', async ({ page }) => {
    await login(page, USERS.admin);
    await openContent(page, 'Checklist sebelum meluncurkan website');

    const dialog = await openDetailSurface(page, 'Arsipkan');
    await dialog.getByLabel('Alasan (opsional)').fill('Digabung ke posting lain');
    await dialog.getByRole('button', { name: 'Arsipkan' }).click();
    await expect(page.getByTestId('archive-summary')).toContainText('Alasan: Digabung ke posting lain');
    await expect(page.getByRole('main').getByRole('textbox')).toHaveCount(0);
    await page.getByTestId('detail-menu').click();
    await expect(page.getByRole('menuitem')).toHaveText(['Riset & sumber', 'Riwayat versi', 'Aktivitas']);
});

test('content pages have no accessibility violations', async ({ page }) => {
    await login(page, USERS.admin);
    for (const path of ['/content/calendar', '/content/calendar?view=list', '/content/calendar?view=list&status=ARCHIVED']) {
        await page.goto(path);
        await settled(page);
        await expectNoAxeViolations(page);
    }
    await openContent(page, 'Domain, hosting, dan SSL dalam satu menit');
    await settled(page);
    await expectNoAxeViolations(page);

    await openContent(page, '3 tanda website Anda perlu diperbarui');
    await settled(page);
    await expectNoAxeViolations(page);
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
