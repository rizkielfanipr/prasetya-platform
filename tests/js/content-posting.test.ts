import PublishedContentWorkspace from '@/features/content/detail/PublishedContentWorkspace.vue';
import ReadyPostingWorkspace from '@/features/content/detail/ReadyPostingWorkspace.vue';
import { dueLabel } from '@/lib/content';
import type { ContentActions, ContentItem, ContentPublication, ContentRevision } from '@/types/content';
import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const item = (overrides: Partial<ContentItem> = {}): ContentItem =>
    ({
        id: 'item-1',
        title: 'Judul',
        status: 'READY',
        dueState: 'overdue',
        daysLate: 2,
        plannedAt: '2026-11-09T03:00:00Z',
        publication: null,
        ...overrides,
    }) as ContentItem;
const revision = (overrides: Partial<ContentRevision> = {}): ContentRevision =>
    ({ id: 'rev-1', number: 1, caption: 'Caption', hashtags: '#a', altText: 'Alt', asset: null, ...overrides }) as unknown as ContentRevision;
const allowed = { markPublished: null, edit: null } as ContentActions;
const mountReady = (i: ContentItem = item(), actions: ContentActions = allowed, rev: ContentRevision = revision()) =>
    mount(ReadyPostingWorkspace, {
        props: { item: i, revision: rev, actions, findings: [], brandRulesVersion: 1 },
        global: { stubs: { Link: RouterLinkStub } },
        attachTo: document.body,
    });
const button = (wrapper: ReturnType<typeof mountReady>, text: string) => wrapper.findAll('button').find((b) => b.text().includes(text));

afterEach(() => {
    document.body.innerHTML = '';
});

describe('dueLabel', () => {
    it('words the reminder', () => {
        expect(dueLabel('overdue', 2)).toBe('Terlambat 2 hari');
        expect(dueLabel('today')).toBe('Jadwal hari ini');
        expect(dueLabel('upcoming')).toBeNull();
        expect(dueLabel(null)).toBeNull();
    });
});

describe('ReadyPostingWorkspace', () => {
    it('opens on the posting package with the lateness in words, and no editor', () => {
        const wrapper = mountReady();

        expect(wrapper.get('[data-testid="due-banner"]').text()).toContain('Terlambat 2 hari');
        expect(wrapper.find('textarea').exists()).toBe(false);
        expect(wrapper.find('#paket-posting').exists()).toBe(true);
    });

    it('has one primary action, Tandai sudah diposting, and no checklist', () => {
        const wrapper = mountReady();
        const primaries = wrapper.findAll('button').filter((b) => b.classes().includes('bg-primary-strong'));

        expect(primaries.map((b) => b.text())).toEqual(['Tandai sudah diposting']);
        expect(button(wrapper, 'Tandai sudah diposting')).toBeDefined();
        expect(wrapper.find('[type="checkbox"], [role="checkbox"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Checklist');
    });

    it('copies the caption with hashtags in one action and keeps hashtags and alt text under Salin lainnya', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.assign(navigator, { clipboard: { writeText } });
        const wrapper = mountReady(item(), allowed, revision({ caption: 'Caption', hashtags: '#a #b' }));
        const labels = wrapper.findAll('button').map((b) => b.text());

        expect(labels).toContain('Salin caption');
        expect(labels).toContain('Salin lainnya');
        expect(labels).not.toContain('Salin hashtag');
        await button(wrapper, 'Salin caption')?.trigger('click');
        await flushPromises();
        expect(writeText).toHaveBeenCalledWith('Caption\n\n#a #b');
    });

    it('explains why a post cannot be marked yet', () => {
        const wrapper = mountReady(item(), { ...allowed, markPublished: 'Tandai siap posting dulu sebelum mencatat posting.' });

        expect(wrapper.get('[data-testid="mark-published"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('#reason-markPublished').text()).toBe('Tandai siap posting dulu sebelum mencatat posting.');
    });

    it('asks before editing, saying the save returns the post to Draft, and only then opens the editor', async () => {
        const wrapper = mountReady(item(), allowed, revision({ number: 3 }));

        await wrapper.get('[data-testid="edit-ready"]').trigger('click');
        await flushPromises();
        const dialog = document.body.querySelector('[role="alertdialog"]');
        expect(dialog?.textContent).toContain('Revisi 3 tetap tersimpan');
        expect(dialog?.textContent).toContain('mengembalikan konten ke Draft');
        expect(wrapper.emitted('edit')).toBeUndefined();

        const confirm = [...(dialog?.querySelectorAll('button') ?? [])].find((b) => b.textContent.trim() === 'Edit konten');
        confirm?.click();
        await flushPromises();
        expect(wrapper.emitted('edit')).toHaveLength(1);
    });
});

describe('PublishedContentWorkspace', () => {
    const publication = (overrides: Partial<ContentPublication> = {}): ContentPublication => ({
        publishedAt: '2026-11-10T05:00:00Z',
        url: 'https://www.instagram.com/p/UJI/',
        note: 'Diposting dari ponsel',
        checklist: null,
        actor: 'Admin',
        revisionId: 'rev-1',
        revisionNumber: 1,
        recordedAt: '2026-11-10T05:01:00Z',
        ...overrides,
    });
    const mountPublished = (p: ContentPublication, posted: ContentRevision = revision({ caption: 'Caption yang diposting' })) =>
        mount(PublishedContentWorkspace, {
            props: { item: item({ status: 'PUBLISHED', dueState: null, publication: p }), publication: p, postedRevision: posted, insights: [] },
        });

    it('shows the evidence and the revision that was posted, with Catat insight as the one primary action', () => {
        const wrapper = mountPublished(publication());

        const evidence = wrapper.get('[data-testid="publication"]').text();
        expect(evidence).toContain('Diposting');
        expect(evidence).toContain('Admin');
        expect(evidence).toContain('Revisi 1');
        expect(evidence).toContain('Diposting dari ponsel');
        expect(wrapper.get('[data-testid="publication"] a').attributes('rel')).toBe('noopener noreferrer');
        expect(wrapper.get('[data-testid="snapshot-caption"]').text()).toBe('Caption yang diposting');
        expect(wrapper.find('textarea').exists()).toBe(false);
        expect(
            wrapper
                .findAll('button')
                .filter((b) => b.classes().includes('bg-primary-strong'))
                .map((b) => b.text()),
        ).toEqual(['Catat insight']);
        expect(wrapper.text()).not.toContain('Tandai sudah diposting');
    });

    it('does not assume an Instagram URL, and keeps a legacy checklist as evidence', () => {
        const wrapper = mountPublished(publication({ url: null, note: null, checklist: ['image', 'caption', 'alt_text', 'account'] }));

        expect(wrapper.get('[data-testid="publication"]').text()).toContain('URL tidak dicatat.');
        expect(wrapper.get('[data-testid="publication"]').text()).toContain('Checklist posting dikonfirmasi (4/4)');
        expect(wrapper.find('[data-testid="publication"] a').exists()).toBe(false);
    });
});
