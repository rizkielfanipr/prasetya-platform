import ContentEditor from '@/features/content/ContentEditor.vue';
import ContentRevisionHistory from '@/features/content/ContentRevisionHistory.vue';
import ContentStatusPanel from '@/features/content/ContentStatusPanel.vue';
import ContentWeekView from '@/features/content/ContentWeekView.vue';
import CopyButton from '@/features/content/CopyButton.vue';
import { contentStatuses, formatWeekday, hashtagCount, revisionChanges, toLocalInput } from '@/lib/content';
import type { ContentActions, ContentItem, ContentRevision, ContentRow, ContentStatus, ContentWeek } from '@/types/content';
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';

const stubs = { Link: RouterLinkStub };

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
    submittedBy: 'Penulis',
    submittedAt: '2026-11-07T03:00:00Z',
    brandRulesVersion: null,
    generatedBy: null,
    createdAt: '2026-11-07T02:00:00Z',
    updatedAt: '2026-11-07T03:00:00Z',
    asset: {
        id: '01ASSET0000000000000000000',
        url: '/content/assets/01ASSET0000000000000000000',
        downloadUrl: '/content/assets/01ASSET0000000000000000000?download=1',
        fileName: 'konten-01ASSET.png',
        mimeType: 'image/png',
        sizeBytes: 20480,
        width: 1080,
        height: 1350,
        checksum: 'aaa',
    },
    decisions: [],
    ...overrides,
});

const row = (overrides: Partial<ContentRow> = {}): ContentRow => ({
    id: '01ITEM00000000000000000000',
    title: 'Kenapa website lambat',
    status: 'DRAFT',
    brand: { code: 'WEBWORKS', name: 'Prasetya Webworks' },
    pillar: null,
    campaign: null,
    topic: null,
    plannedAt: '2026-11-09T03:00:00Z',
    plannedDay: '2026-11-09',
    revisionNumber: 1,
    dueState: null,
    hasImage: true,
    updatedAt: '2026-11-07T03:00:00Z',
    ...overrides,
});

const item = (status: ContentStatus): ContentItem => ({
    ...row({ status }),
    createdAt: '2026-11-07T02:00:00Z',
    createdBy: 'Penulis',
    readyRevisionId: null,
    approvedRevisionId: null,
    publication: null,
});

const allowed: ContentActions = { edit: null, editDetails: null, markReady: null, returnToDraft: null, archive: null, markPublished: null };

describe('content helpers', () => {
    it('writes datetime-local values in business time', () => {
        expect(toLocalInput('2026-11-09T03:00:00Z')).toBe('2026-11-09T10:00');
        expect(toLocalInput('2026-11-08T17:30:00Z')).toBe('2026-11-09T00:30');
    });

    it('counts hashtags the way the server does', () => {
        expect(hashtagCount('#satu #dua bukan#tiga')).toBe(3);
        expect(hashtagCount('')).toBe(0);
        expect(hashtagCount(null)).toBe(0);
    });

    it('names a business day without shifting it', () => {
        expect(formatWeekday('2026-11-09')).toBe('Senin, 9 November');
        expect(formatWeekday('2026-11-15', true)).toBe('Min 15');
    });

    it('lists the fields and image that changed between two revisions', () => {
        const before = revision();
        const asset = before.asset ? { ...before.asset, checksum: 'bbb' } : null;
        const after = revision({ id: 'b', number: 2, caption: 'Caption kedua', asset });

        expect(revisionChanges(before, after)).toEqual({ fields: ['caption'], image: true });
        expect(revisionChanges(before, revision({ id: 'c' }))).toEqual({ fields: [], image: false });
    });

    it('labels every status', () => {
        expect(Object.keys(contentStatuses)).toEqual(['DRAFT', 'READY', 'PUBLISHED', 'ARCHIVED']);
        expect(contentStatuses.READY.label).toBe('Siap posting');
        expect(contentStatuses.ARCHIVED.label).toBe('Diarsipkan');
    });
});

describe('ContentWeekView', () => {
    const week: ContentWeek = {
        start: '2026-11-09',
        end: '2026-11-15',
        previous: '2026-11-02',
        next: '2026-11-16',
        today: '2026-11-10',
        days: ['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14', '2026-11-15'],
    };

    it('puts each item under its business day and marks today', () => {
        const wrapper = mount(ContentWeekView, {
            props: { week, items: [row(), row({ id: 'x', title: 'Hari Minggu', plannedDay: '2026-11-15', status: 'READY' })] },
            global: { stubs },
        });

        expect(wrapper.findAll('[data-day]')).toHaveLength(7);
        expect(wrapper.get('[data-day="2026-11-09"]').text()).toContain('Kenapa website lambat');
        expect(wrapper.get('[data-day="2026-11-15"]').text()).toContain('Hari Minggu');
        expect(wrapper.get('[data-day="2026-11-15"]').text()).toContain('Siap posting');
        expect(wrapper.get('[data-day="2026-11-10"]').text()).toContain('Hari ini');
        expect(wrapper.get('[data-day="2026-11-11"]').text()).toContain('Belum ada konten');
        expect(wrapper.get('[data-day="2026-11-09"]').text()).toContain('10:00 WIB');
    });
});

describe('ContentStatusPanel', () => {
    it('keeps an unavailable action visible, disabled, and explained', () => {
        const reason = 'Lengkapi caption sebelum menandai siap posting.';
        const wrapper = mount(ContentStatusPanel, {
            props: { item: item('DRAFT'), revision: revision({ submittedAt: null }), actions: { ...allowed, markReady: reason } },
        });

        const ready = wrapper.findAll('button').find((button) => button.text() === 'Tandai siap posting');
        expect(ready?.attributes('disabled')).toBeDefined();
        expect(ready?.attributes('aria-describedby')).toBe('reason-markReady');
        expect(wrapper.get('#reason-markReady').text()).toBe(reason);
        expect(wrapper.findAll('button').map((button) => button.text())).toEqual(['Tandai siap posting', 'Arsipkan']);
    });

    it('offers returning to Draft or archiving once Ready, with no approval step', () => {
        const wrapper = mount(ContentStatusPanel, { props: { item: item('READY'), revision: revision(), actions: allowed } });

        expect(wrapper.findAll('button').map((button) => button.text())).toEqual(['Kembali ke Draft', 'Arsipkan']);
        expect(wrapper.get('[data-testid="status-summary"]').text()).toContain('Revisi 1 siap diposting');
        expect(wrapper.text()).not.toMatch(/setuju|tinjau/i);
    });

    it('offers no action once published or archived', () => {
        for (const status of ['PUBLISHED', 'ARCHIVED'] as const) {
            const wrapper = mount(ContentStatusPanel, { props: { item: item(status), revision: revision(), actions: allowed } });
            expect(wrapper.findAll('button')).toHaveLength(0);
        }
    });
});

describe('ContentEditor', () => {
    it('shows read-only content with the reason when editing is unavailable', () => {
        const reason = 'Konten sudah diposting dan tidak dapat diubah.';
        const wrapper = mount(ContentEditor, {
            props: { item: item('PUBLISHED'), revision: revision(), blockedReason: reason, limits: { maxHashtags: 30, captionMax: 2200 } },
        });

        expect(wrapper.get('[data-testid="edit-blocked"]').text()).toBe(reason);
        expect(wrapper.get('#content-caption').attributes('readonly')).toBeDefined();
        expect(wrapper.find('#content-image').exists()).toBe(false);
        expect(wrapper.find('button[type="submit"]').exists()).toBe(false);
    });

    it('labels required fields, links counters, and warns that editing Ready content returns it to Draft', () => {
        const wrapper = mount(ContentEditor, {
            props: { item: item('READY'), revision: revision(), blockedReason: null, limits: { maxHashtags: 30, captionMax: 2200 } },
        });

        expect(wrapper.text()).toContain('Caption *');
        expect(wrapper.text()).toContain('mengembalikannya ke Draft');
        expect(wrapper.get('#content-caption').attributes('aria-describedby')).toContain('content-caption-count');
        expect(wrapper.get('#content-hashtags-count').text()).toBe('2/30 hashtag');
        expect(wrapper.get('#content-image').attributes('accept')).toBe('image/jpeg,image/png,image/webp');
    });
});

describe('ContentRevisionHistory', () => {
    it('lists revisions with decisions and compares the two newest', async () => {
        const first = revision({
            decisions: [{ decision: 'REVISION_REQUESTED', areas: ['caption'], note: 'Ringkas caption', actor: 'Peninjau', at: '2026-11-07T04:00:00Z' }],
        });
        const second = revision({ id: '01REVISION000000000000000B', number: 2, caption: 'Caption ringkas', submittedAt: null });
        const wrapper = mount(ContentRevisionHistory, { props: { revisions: [second, first], readyRevisionId: null, approvedRevisionId: null } });
        await flushPromises();

        expect(wrapper.get('[data-testid="revision-1"]').text()).toContain('Revisi diminta (Caption) oleh Peninjau');
        expect(wrapper.get('[data-testid="revision-1"]').text()).toContain('Ringkas caption');
        expect(wrapper.get('[data-testid="revision-2"]').text()).toContain('Masih draft');
        const diff = wrapper.get('[data-testid="revision-diff"]').text();
        expect(diff).toContain('Caption');
        expect(diff).toContain('Caption pertama');
        expect(diff).toContain('Caption ringkas');
        expect(diff).not.toContain('Brief');
    });
});

describe('CopyButton', () => {
    it('copies the text and announces it', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText } });
        const wrapper = mount(CopyButton, { props: { text: 'Caption pertama', label: 'Salin caption', done: 'Caption disalin.' } });

        await wrapper.get('button').trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith('Caption pertama');
        expect(wrapper.get('[aria-live="polite"]').text()).toBe('Caption disalin.');
        expect(wrapper.get('button').text()).toBe('Disalin');
    });
});
