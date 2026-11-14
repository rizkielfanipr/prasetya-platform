import { expect, type Page } from '@playwright/test';
import { crc32, deflateSync } from 'node:zlib';
import { expectNoAxeViolations, login, openContent, openDetailSurface, test, USERS } from './support';

/* ADR-0013: the detail page is a workspace per status, with evidence one named action away. */

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
    const row = Buffer.concat([Buffer.from([0]), Buffer.alloc(width * 3, 0x40)]);

    return Buffer.concat([
        Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
        chunk('IHDR', header),
        chunk('IDAT', deflateSync(Buffer.concat(Array.from({ length: height }, () => row)))),
        chunk('IEND', Buffer.alloc(0)),
    ]);
}

async function settled(page: Page): Promise<void> {
    await page.waitForLoadState('networkidle');
    await page.evaluate(() => Promise.allSettled(document.getAnimations().map((animation) => animation.finished)));
}

async function newDraft(page: Page, idea: string): Promise<void> {
    await page.goto('/content/calendar');
    await page.getByRole('button', { name: 'Tambah konten' }).click();
    const create = page.getByRole('alertdialog', { name: 'Tambah konten' });
    await create.getByLabel('Ide atau topik *').fill(idea);
    await create.getByRole('button', { name: 'Buat draft' }).click();
    await expect(page.getByRole('heading', { level: 1, name: idea })).toBeVisible();
}

test('a manual Draft reaches Ready from the main flow: missing alt text and a brand violation block it, a warning does not', async ({ page }) => {
    await login(page, USERS.admin);
    await newDraft(page, `Manual ${Date.now().toString(36)}`);

    await page.getByLabel('Caption *').fill('Paket website termurah untuk UMKM.');
    await page.getByRole('textbox', { name: 'Hashtag', exact: true }).fill('#prasetyawebworks #websiteumkm');
    await page.getByLabel('Unggah gambar').setInputFiles({ name: 'post.png', mimeType: 'image/png', buffer: png(1080, 1350) });
    await page.getByTestId('save-content').click();
    await expect(page.getByText('Revisi 1 disimpan.')).toBeVisible();

    // Missing alt text and a forbidden word: both named in the rail, Ready stays disabled with the server's reason.
    await expect(page.getByTestId('readiness-alt_text')).toHaveText('Alt text belum diisi');
    await expect(page.getByTestId('readiness-brand')).toHaveText('1 pelanggaran aturan brand');
    await expect(page.getByTestId('brand-errors')).toContainText('1 pelanggaran harus diperbaiki');
    await expect(page.getByTestId('rule-findings')).toContainText('termurah');
    await expect(page.getByTestId('mark-ready')).toBeDisabled();

    await page.getByLabel('Alt text *').fill('Kartu biru bertuliskan paket website untuk UMKM.');
    await page.getByTestId('save-content').click();
    await expect(page.getByTestId('readiness-alt_text')).toHaveText('Alt text sudah diisi');
    await expect(page.locator('#reason-markReady')).toHaveText('Perbaiki 1 pelanggaran aturan brand sebelum menandai siap posting.');

    // Without the forbidden word only a warning is left (no approved CTA): it informs and does not block.
    await page.getByLabel('Caption *').fill('Paket website untuk UMKM yang baru online.');
    await page.getByTestId('save-content').click();
    await expect(page.getByTestId('brand-warnings')).toContainText('Tidak menghalangi siap posting');
    await page.getByRole('button', { name: 'Lihat saran' }).click();
    await expect(page.locator('#brand-findings-list')).toContainText('Peringatan');
    await expect(page.getByTestId('readiness-count')).toHaveText('5 dari 5');

    await page.getByTestId('mark-ready').click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Tandai siap posting' }).click();
    await expect(page.getByRole('heading', { name: 'Paket posting' })).toBeVisible();
    await page.getByRole('button', { name: 'Salin lainnya' }).click();
    await expect(page.getByRole('menuitem')).toHaveText(['Salin hashtag', 'Salin alt text']);
    await page.keyboard.press('Escape');
});

test('Riset & sumber, Riwayat versi, and Aktivitas open from the keyboard and give focus back', async ({ page }) => {
    await login(page, USERS.admin);
    await openContent(page, 'Domain, hosting, dan SSL dalam satu menit');
    const menu = page.getByTestId('detail-menu');

    for (const name of ['Riset & sumber', 'Riwayat versi', 'Aktivitas']) {
        await menu.focus();
        await page.keyboard.press('Enter');
        await expect(page.getByRole('menu')).toBeVisible();
        for (let step = 0; step < 8; step++) {
            if (await page.getByRole('menuitem', { name, exact: true }).evaluate((element) => element === document.activeElement)) break;
            await page.keyboard.press('ArrowDown');
        }
        await page.keyboard.press('Enter');
        const sheet = page.getByRole('dialog', { name });
        await expect(sheet).toBeVisible();
        await expect(sheet.getByRole('button', { name: 'Tutup' })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(sheet).toBeHidden();
        await expect(menu).toBeFocused();
    }
});

test('unsaved Draft edits survive opening and closing the evidence sheets', async ({ page }) => {
    await login(page, USERS.admin);
    await openContent(page, 'Domain, hosting, dan SSL dalam satu menit');

    await page.getByLabel('Caption *').fill('Perubahan yang belum disimpan.');
    await expect(page.getByTestId('readiness-saved')).toHaveText('Ada perubahan yang belum disimpan');
    for (const name of ['Riwayat versi', 'Riset & sumber', 'Aktivitas']) {
        const sheet = await openDetailSurface(page, name);
        await expect(sheet.getByRole('status')).toHaveCount(0, { timeout: 10_000 });
        await page.keyboard.press('Escape');
    }

    await expect(page.getByLabel('Caption *')).toHaveValue('Perubahan yang belum disimpan.');
    await expect(page.getByTestId('save-content')).toBeVisible();
});

test.describe('when evidence cannot load', () => {
    test.use({ allowedConsole: [/status of 500/] });

    test('the sheet says so and retries, and the workspace keeps working', async ({ page }) => {
        await login(page, USERS.admin);
        await openContent(page, 'Domain, hosting, dan SSL dalam satu menit');
        await page.route('**/content/items/*/history', (route) => route.fulfill({ status: 500, body: 'error' }));

        const history = await openDetailSurface(page, 'Riwayat versi');
        await expect(history.getByRole('alert')).toContainText('Data tidak dapat dimuat');
        await page.unroute('**/content/items/*/history');
        await history.getByRole('button', { name: 'Coba lagi' }).click();
        await expect(history.getByTestId('revision-1')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByLabel('Caption *')).toBeEditable();
    });
});

test('every status workspace and every sheet passes axe', async ({ page }) => {
    await login(page, USERS.admin);

    await openContent(page, 'Domain, hosting, dan SSL dalam satu menit');
    for (const name of ['Ubah rencana', 'Detail kreatif', 'Riset & sumber', 'Riwayat versi', 'Aktivitas']) {
        await openDetailSurface(page, name);
        await settled(page);
        await expectNoAxeViolations(page);
        await page.keyboard.press('Escape');
    }

    await openContent(page, 'Kenapa website lambat merugikan bisnis');
    await expect(page.getByTestId('published-workspace')).toBeVisible();
    await settled(page);
    await expectNoAxeViolations(page);

    await openContent(page, 'Promo lama yang dibatalkan', true);
    await expect(page.getByTestId('archived-workspace')).toBeVisible();
    await expect(page.getByRole('main').getByRole('textbox')).toHaveCount(0);
    await settled(page);
    await expectNoAxeViolations(page);
    await page.getByRole('button', { name: 'Lihat riwayat versi' }).click();
    await expect(page.getByRole('dialog', { name: 'Riwayat versi' })).toBeVisible();
    await settled(page);
    await expectNoAxeViolations(page);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('button', { name: 'Lihat riwayat versi' })).toBeFocused();
});

test('at 200% zoom the primary action of each workspace stays reachable, without sideways scrolling', async ({ page }) => {
    // 1280×900 at 200% leaves a 640×450 CSS viewport.
    await page.setViewportSize({ width: 640, height: 450 });
    await login(page, USERS.admin);

    for (const [title, action] of [
        ['Domain, hosting, dan SSL dalam satu menit', 'mark-ready'],
        ['3 tanda website Anda perlu diperbarui', 'mark-published'],
    ] as const) {
        await openContent(page, title);
        const button = page.getByTestId(action);
        await button.scrollIntoViewIfNeeded();
        await expect(button).toBeInViewport();
        expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth)).toBeLessThanOrEqual(0);
    }
});

test('with reduced motion the sheets appear without moving', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await login(page, USERS.admin);
    await openContent(page, 'Domain, hosting, dan SSL dalam satu menit');

    const sheet = await openDetailSurface(page, 'Aktivitas');
    const duration = await sheet.evaluate((element) => parseFloat(getComputedStyle(element).animationDuration));
    expect(duration).toBeLessThanOrEqual(0.00001);
});
