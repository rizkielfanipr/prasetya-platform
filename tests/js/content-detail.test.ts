import BrandCheckSummary from '@/features/content/detail/BrandCheckSummary.vue';
import ContentActivitySheet from '@/features/content/detail/ContentActivitySheet.vue';
import ContentDetailHeader from '@/features/content/detail/ContentDetailHeader.vue';
import ContentMediaEditor from '@/features/content/detail/ContentMediaEditor.vue';
import ContentReadinessPanel from '@/features/content/detail/ContentReadinessPanel.vue';
import DraftContentWorkspace from '@/features/content/detail/DraftContentWorkspace.vue';
import ContentDetailPage from '@/pages/content/ContentDetailPage.vue';
import type { ContentActions, ContentItem, ContentRevision, ContentStatus, RuleFinding } from '@/types/content';
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, nextTick } from 'vue';

const inertia = vi.hoisted(() => ({ reload: vi.fn(), post: vi.fn(), on: vi.fn(() => () => undefined) }));
vi.mock('@inertiajs/vue3', async (original) => ({ ...(await original<object>()), router: inertia }));

const AppLayout = defineComponent({
    props: { title: { type: String, default: '' }, description: { type: String, default: '' } },
    setup:
        (_, { slots }) =>
        () =>
            h('main', slots.default?.()),
});
const stubs = { Link: RouterLinkStub, AppLayout };

const revision = (overrides: Partial<ContentRevision> = {}): ContentRevision => ({
    id: '01REVISION000000000000000A',
    number: 1,
    objective: null,
    audience: null,
    brief: 'Satu gambar.',
    caption: 'Caption pertama',
    hashtags: '#satu #dua',
    altText: 'Kartu oranye.',
    visualDirection: null,
    author: 'Penulis',
    submittedBy: null,
    submittedAt: null,
    brandRulesVersion: null,
    generatedBy: null,
    createdAt: '2026-11-07T02:00:00Z',
    updatedAt: '2026-11-07T03:00:00Z',
    asset: {
        id: '01ASSET0000000000000000000',
        url: '/content/assets/01ASSET0000000000000000000',
        downloadUrl: '/content/assets/01ASSET0000000000000000000?download=1',
        fileName: 'konten.png',
        mimeType: 'image/png',
        sizeBytes: 20480,
        width: 1080,
        height: 1350,
        checksum: 'aaa',
    },
    decisions: [],
    ...overrides,
});

const item = (status: ContentStatus, overrides: Partial<ContentItem> = {}): ContentItem => ({
    id: '01ITEM00000000000000000000',
    title: 'Kenapa website lambat',
    status,
    brand: { code: 'WEBWORKS', name: 'Prasetya Webworks' },
    pillar: null,
    campaign: null,
    topic: null,
    plannedAt: '2026-11-09T03:00:00Z',
    plannedDay: '2026-11-09',
    revisionNumber: 1,
    dueState: null,
    daysLate: null,
    hasImage: true,
    updatedAt: '2026-11-07T03:00:00Z',
    createdAt: '2026-11-07T02:00:00Z',
    createdBy: 'Penulis',
    readyRevisionId: null,
    approvedRevisionId: null,
    publication: null,
    ...overrides,
});

const allowed: ContentActions = { edit: null, editDetails: null, markReady: null, returnToDraft: null, archive: null, markPublished: null };
const finding = (severity: 'ERROR' | 'WARNING', message = 'Pesan.'): RuleFinding => ({ code: severity.toLowerCase(), severity, message, field: 'caption' });
const limits = { maxHashtags: 30, captionMax: 2200 };

beforeEach(() => vi.clearAllMocks());
afterEach(() => {
    vi.unstubAllGlobals();
    document.body.innerHTML = '';
});

describe('ContentDetailPage', () => {
    const pageProps = (status: ContentStatus) => {
        const editable = status === 'DRAFT' || status === 'READY';
        const publication =
            status === 'PUBLISHED'
                ? {
                      publishedAt: '2026-11-10T05:00:00Z',
                      url: null,
                      note: null,
                      checklist: null,
                      actor: 'Admin',
                      revisionId: 'r',
                      revisionNumber: 1,
                      recordedAt: '2026-11-10T05:00:00Z',
                  }
                : null;

        return {
            item: item(status, { publication }),
            revision: revision(),
            actions: allowed,
            limits: editable ? limits : null,
            findings: editable ? [] : null,
            brandRulesVersion: editable ? 1 : null,
            planOptions: editable ? { pillars: [], campaigns: [] } : null,
            ai: status === 'DRAFT' ? { unavailableReason: null, current: null } : null,
            postedRevision: status === 'PUBLISHED' ? revision() : null,
            insights: status === 'PUBLISHED' ? [] : null,
            archive: status === 'ARCHIVED' ? { at: '2026-11-10T05:00:00Z', actor: 'Admin', reason: 'Batal' } : null,
        };
    };

    it.each([
        ['DRAFT', 'draft-workspace'],
        ['READY', 'ready-workspace'],
        ['PUBLISHED', 'published-workspace'],
        ['ARCHIVED', 'archived-workspace'],
    ] as const)('renders only the %s workspace', (status, testid) => {
        const wrapper = mount(ContentDetailPage, { props: pageProps(status), global: { stubs } });
        const workspaces = ['draft-workspace', 'ready-workspace', 'published-workspace', 'archived-workspace'];

        expect(workspaces.filter((id) => wrapper.find(`[data-testid="${id}"]`).exists())).toEqual([testid]);
        expect(wrapper.find('textarea').exists()).toBe(status === 'DRAFT');
        expect(wrapper.text()).not.toContain('Pengaturan tambahan');
    });

    it('opens the editor on Ready only after Edit konten, and goes back to the posting package', async () => {
        const wrapper = mount(ContentDetailPage, { props: pageProps('READY'), global: { stubs }, attachTo: document.body });

        await wrapper.get('[data-testid="edit-ready"]').trigger('click');
        await flushPromises();
        [...document.body.querySelectorAll('[role="alertdialog"] button')]
            .find((b) => b.textContent.trim() === 'Edit konten')
            ?.dispatchEvent(new Event('click'));
        await flushPromises();

        expect(wrapper.find('[data-testid="draft-workspace"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="ready-edit-note"]').text()).toContain('membuat revisi baru dan mengembalikan konten ke Draft');
        expect(wrapper.find('[data-testid="ai-panel"]').exists()).toBe(false);

        await wrapper
            .findAll('button')
            .find((b) => b.text() === 'Kembali ke paket posting')
            ?.trigger('click');
        expect(wrapper.find('[data-testid="ready-workspace"]').exists()).toBe(true);
    });
});

describe('ContentDetailHeader', () => {
    const openMenu = async (status: ContentStatus, actions: ContentActions = allowed, overrides: Partial<ContentItem> = {}) => {
        const wrapper = mount(ContentDetailHeader, {
            props: { item: item(status, overrides), actions, editing: false },
            global: { stubs },
            attachTo: document.body,
        });
        await wrapper.get('[data-testid="detail-menu"]').trigger('keydown', { key: 'Enter' });
        await flushPromises();

        return { wrapper, entries: [...document.body.querySelectorAll('[role="menuitem"]')].map((entry) => entry.textContent.trim()) };
    };

    it('links back to the item week and states the status and lateness in words', () => {
        const wrapper = mount(ContentDetailHeader, {
            props: { item: item('READY', { dueState: 'overdue', daysLate: 2 }), actions: allowed, editing: false },
            global: { stubs },
        });

        expect(wrapper.get('a').attributes('href')).toBe('/content/calendar?week=2026-11-09');
        expect(wrapper.get('a').text()).toBe('Kembali ke Kalender');
        expect(wrapper.get('[data-testid="detail-status"]').text()).toContain('Siap posting');
        expect(wrapper.get('[data-testid="detail-due"]').text()).toBe('Terlambat 2 hari');
    });

    it('offers each status only what the server allows', async () => {
        expect((await openMenu('DRAFT')).entries).toEqual(['Ubah rencana', 'Detail kreatif', 'Riset & sumber', 'Riwayat versi', 'Aktivitas', 'Arsipkan']);
        document.body.innerHTML = '';
        expect((await openMenu('READY')).entries).toEqual(['Ubah rencana', 'Riset & sumber', 'Riwayat versi', 'Aktivitas', 'Kembali ke Draft', 'Arsipkan']);
        document.body.innerHTML = '';
        const closed = { ...allowed, edit: 'x', editDetails: 'x', archive: 'x', returnToDraft: 'x', markReady: 'x', markPublished: 'x' };
        expect((await openMenu('PUBLISHED', closed)).entries).toEqual(['Riset & sumber', 'Riwayat versi', 'Aktivitas']);
        document.body.innerHTML = '';
        expect((await openMenu('ARCHIVED', closed)).entries).toEqual(['Riset & sumber', 'Riwayat versi', 'Aktivitas']);
    });

    it('does not offer a Ready plan change the server refuses', async () => {
        expect((await openMenu('READY', { ...allowed, editDetails: 'Tidak boleh.' })).entries).not.toContain('Ubah rencana');
    });
});

describe('ContentReadinessPanel', () => {
    const mountPanel = (overrides: Record<string, unknown> = {}) =>
        mount(ContentReadinessPanel, {
            props: {
                item: item('DRAFT'),
                revision: revision(),
                findings: [],
                actions: allowed,
                dirty: false,
                saving: false,
                aiActive: false,
                errors: {},
                editingReady: false,
                ...overrides,
            },
            attachTo: document.body,
        });
    const done = (wrapper: ReturnType<typeof mountPanel>) =>
        wrapper.findAll('li[data-done]').map((row) => `${row.attributes('data-testid')}:${row.attributes('data-done')}`);

    it('offers Tandai siap posting when the saved revision is complete', () => {
        const wrapper = mountPanel();

        expect(done(wrapper)).toEqual([
            'readiness-caption:true',
            'readiness-image:true',
            'readiness-alt_text:true',
            'readiness-brand:true',
            'readiness-saved:true',
        ]);
        expect(wrapper.get('[data-testid="readiness-count"]').text()).toBe('5 dari 5');
        expect(wrapper.get('[data-testid="mark-ready"]').attributes('disabled')).toBeUndefined();
    });

    it('names what is missing in words and keeps Ready disabled with the server reason', () => {
        const reason = 'Lengkapi alt text dan gambar sebelum menandai siap posting.';
        const wrapper = mountPanel({ revision: revision({ altText: null, asset: null }), actions: { ...allowed, markReady: reason } });

        expect(wrapper.get('[data-testid="readiness-alt_text"]').text()).toBe('Alt text belum diisi');
        expect(wrapper.get('[data-testid="readiness-image"]').text()).toBe('Gambar belum diunggah');
        expect(wrapper.get('[data-testid="mark-ready"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-testid="mark-ready"]').attributes('aria-describedby')).toBe('reason-markReady');
        expect(wrapper.get('#reason-markReady').text()).toBe(reason);
    });

    it('makes saving the one action while there are unsaved changes', () => {
        const wrapper = mountPanel({ dirty: true });

        expect(wrapper.get('[data-testid="readiness-saved"]').text()).toBe('Ada perubahan yang belum disimpan');
        expect(wrapper.find('[data-testid="mark-ready"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="save-content"]').text()).toBe('Simpan perubahan');
    });

    it('counts blocking brand errors as a missing requirement', () => {
        const wrapper = mountPanel({
            findings: [finding('ERROR'), finding('ERROR'), finding('WARNING')],
            actions: { ...allowed, markReady: 'Perbaiki 2 pelanggaran.' },
        });

        expect(wrapper.get('[data-testid="readiness-brand"]').text()).toBe('2 pelanggaran aturan brand');
        expect(wrapper.get('[data-testid="mark-ready"]').attributes('disabled')).toBeDefined();
    });

    it('shows a busy, disabled action while saving or while AI writes', () => {
        for (const [state, label] of [
            [{ saving: true, dirty: true }, 'Menyimpan…'],
            [{ aiActive: true }, 'AI sedang menulis…'],
        ] as const) {
            const button = mountPanel(state).get('button');
            expect(button.text()).toBe(label);
            expect(button.attributes('disabled')).toBeDefined();
            expect(button.attributes('aria-busy')).toBe('true');
        }
    });

    it('sums up errors with links that move to the same fields', async () => {
        const wrapper = mountPanel({ errors: { alt_text: 'Alt text maksimal 1000 karakter.', image: 'Gambar harus 4:5.' } });

        const summary = wrapper.get('[data-testid="error-summary"]');
        expect(summary.attributes('role')).toBe('alert');
        expect(summary.text()).toContain('Alt text: Alt text maksimal 1000 karakter.');
        await summary.get('a[href="#content-alt_text"]').trigger('click');
        expect(wrapper.emitted('focusField')?.[0]).toEqual(['alt_text']);
    });

    it('announces progress politely, not every keystroke', async () => {
        const wrapper = mountPanel();
        await wrapper.setProps({ dirty: true });
        expect(wrapper.get('[aria-live="polite"]').text()).toBe('Ada perubahan yang belum disimpan.');
    });
});

describe('BrandCheckSummary', () => {
    const mountSummary = (findings: RuleFinding[], version: number | null = 2) => mount(BrandCheckSummary, { props: { findings, version }, global: { stubs } });

    it('is one line when the check is clean', () => {
        const wrapper = mountSummary([]);
        expect(wrapper.get('[data-testid="brand-clean"]').text()).toBe('Sesuai aturan brand versi 2.');
        expect(wrapper.findAll('li')).toHaveLength(0);
    });

    it('is neutral, not a success, when there are no rules yet', () => {
        const wrapper = mountSummary([{ code: 'no_profile', severity: 'WARNING', message: 'Aturan brand belum diisi.', field: null }], null);
        expect(wrapper.get('[data-testid="brand-none"]').text()).toContain('Aturan brand belum diisi');
        expect(wrapper.find('[data-testid="brand-clean"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="brand-warnings"]').exists()).toBe(false);
    });

    it('counts warnings and shows them only on request', async () => {
        const wrapper = mountSummary([finding('WARNING', 'Caption belum memakai CTA.')]);
        const toggle = wrapper.get('button');

        expect(wrapper.get('[data-testid="brand-warnings"]').text()).toContain('1 saran');
        expect(wrapper.get('#brand-findings-list').isVisible()).toBe(false);
        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('#brand-findings-list').text()).toContain('Peringatan');
    });

    it('keeps blocking errors open, before warnings, in words', () => {
        const wrapper = mountSummary([finding('WARNING', 'Saran.'), finding('ERROR', 'Kata terlarang "murah".')]);
        const rows = wrapper.findAll('li').map((li) => li.text());

        expect(wrapper.get('[data-testid="brand-errors"]').text()).toContain('1 pelanggaran harus diperbaiki');
        expect(wrapper.get('#brand-findings-list').isVisible()).toBe(true);
        expect(rows[0]).toContain('Pelanggaran');
        expect(rows[1]).toContain('Peringatan');
        expect(wrapper.find('button').exists()).toBe(false);
    });
});

describe('ContentMediaEditor', () => {
    it('keeps alt text with the image and shows both errors without any disclosure', () => {
        const wrapper = mount(ContentMediaEditor, {
            props: {
                asset: revision().asset,
                readonly: false,
                errors: { image: 'Gambar harus 4:5.', alt_text: 'Wajib.' },
                visualDirection: 'Latar polos.',
                altText: '',
            },
        });

        const media = wrapper.get('[data-testid="media-area"]');
        expect(media.find('#content-alt_text').exists()).toBe(true);
        expect(media.find('#content-image').exists()).toBe(true);
        expect(wrapper.get('#content-image').attributes('aria-describedby')).toContain('content-image-error');
        expect(wrapper.get('#content-image-error').text()).toBe('Gambar harus 4:5.');
        expect(wrapper.get('#content-alt_text').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('label[for="content-image"]').text()).toBe('Ganti gambar');
        expect(wrapper.get('[data-testid="visual-direction"]').text()).toContain('Latar polos.');
    });
});

describe('DraftContentWorkspace', () => {
    const mountDraft = (overrides: Record<string, unknown> = {}) =>
        mount(DraftContentWorkspace, {
            props: {
                item: item('DRAFT'),
                revision: revision(),
                actions: allowed,
                limits,
                findings: [],
                brandRulesVersion: 1,
                ai: { unavailableReason: null, current: null },
                editingReady: false,
                ...overrides,
            },
            global: { stubs },
            attachTo: document.body,
        });

    it('puts the whole job in one flow: brief, AI, caption with hashtags, media with alt text, brand check, readiness', () => {
        const html = mountDraft().html();
        const at = (needle: string) => html.indexOf(needle);

        expect(at('id="content-brief"')).toBeLessThan(at('data-testid="ai-panel"'));
        expect(at('data-testid="ai-panel"')).toBeLessThan(at('id="content-caption"'));
        expect(at('id="content-caption"')).toBeLessThan(at('id="content-hashtags"'));
        expect(at('id="content-hashtags"')).toBeLessThan(at('data-testid="media-area"'));
        expect(at('data-testid="media-area"')).toBeLessThan(at('id="content-alt_text"'));
        expect(at('id="content-alt_text"')).toBeLessThan(at('data-testid="rule-findings"'));
        expect(at('data-testid="rule-findings"')).toBeLessThan(at('data-testid="readiness"'));
    });

    it('marks the screen unsaved and swaps Ready for Simpan perubahan while editing', async () => {
        const wrapper = mountDraft();

        await wrapper.get('#content-caption').setValue('Caption baru');

        expect(wrapper.get('[data-testid="readiness-saved"]').attributes('data-done')).toBe('false');
        expect(wrapper.find('[data-testid="mark-ready"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="save-content"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="rule-findings"]').text()).toContain('Diperiksa dari versi yang terakhir disimpan.');
    });

    it('moves focus to the field an error summary link names', async () => {
        const wrapper = mountDraft();

        wrapper.getComponent(ContentReadinessPanel).vm.$emit('focusField', 'alt_text');
        await flushPromises();

        expect(document.activeElement?.id).toBe('content-alt_text');
    });

    it('keeps the fields read-only while AI writes', () => {
        const wrapper = mountDraft({
            ai: {
                unavailableReason: 'AI sedang membuat draft untuk konten ini.',
                current: { status: 'RUNNING' } as NonNullable<Parameters<typeof mountDraft>[0]>['ai'],
            },
        });

        expect(wrapper.get('#content-caption').attributes('readonly')).toBeDefined();
        expect(wrapper.get('#content-alt_text').attributes('readonly')).toBeDefined();
        expect(wrapper.find('#content-image').exists()).toBe(false);
    });
});

describe('Evidence sheets', () => {
    const json = (body: unknown, status = 200) => ({ ok: status < 400, status, type: 'basic', json: () => Promise.resolve(body) }) as Response;

    it('loads when opened, says it is loading, then shows an empty state', async () => {
        let resolve: (value: Response) => void = () => undefined;
        const fetchMock = vi.fn(() => new Promise<Response>((r) => (resolve = r)));
        vi.stubGlobal('fetch', fetchMock);
        const wrapper = mount(ContentActivitySheet, { props: { item: item('DRAFT'), open: false }, attachTo: document.body });
        expect(fetchMock).not.toHaveBeenCalled();

        await wrapper.setProps({ open: true });
        await nextTick();
        expect(fetchMock).toHaveBeenCalledWith('/content/items/01ITEM00000000000000000000/activity', expect.objectContaining({ redirect: 'manual' }));
        expect(document.body.querySelector('[role="status"]')?.textContent).toContain('Memuat aktivitas');
        expect(inertia.on).toHaveBeenCalledWith('success', expect.any(Function));

        resolve(json({ entries: [] }));
        await flushPromises();
        expect(document.body.querySelector('[data-testid="activity-sheet"]')?.textContent).toContain('Belum ada catatan audit');
    });

    it('shows the failure with a retry that loads again', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(json({}, 500))
            .mockResolvedValueOnce(json({ entries: [] }));
        vi.stubGlobal('fetch', fetchMock);
        mount(ContentActivitySheet, { props: { item: item('DRAFT'), open: true }, attachTo: document.body });
        await flushPromises();

        const alert = document.body.querySelector('[data-testid="activity-sheet"] [role="alert"]');
        expect(alert?.textContent).toContain('Data tidak dapat dimuat');
        [...(alert?.querySelectorAll('button') ?? [])].find((b) => b.textContent.trim() === 'Coba lagi')?.click();
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(document.body.querySelector('[data-testid="activity-sheet"]')?.textContent).toContain('Belum ada catatan audit');
    });

    it('says the session ended when the server redirects to sign-in', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 0, type: 'opaqueredirect' }));
        mount(ContentActivitySheet, { props: { item: item('DRAFT'), open: true }, attachTo: document.body });
        await flushPromises();

        expect(document.body.querySelector('[data-testid="activity-sheet"] [role="alert"]')?.textContent).toContain('Sesi Anda berakhir');
    });

    it('closes with Escape and stops listening for page visits', async () => {
        const stopListening = vi.fn();
        inertia.on.mockReturnValueOnce(stopListening);
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(json({ entries: [] })));
        const wrapper = mount(ContentActivitySheet, { props: { item: item('DRAFT'), open: true }, attachTo: document.body });
        await flushPromises();

        document.body.querySelector('[role="dialog"]')?.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await flushPromises();
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);

        await wrapper.setProps({ open: false });
        expect(stopListening).toHaveBeenCalled();
    });
});
