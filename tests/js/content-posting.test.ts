import ContentPostingPackage from '@/features/content/ContentPostingPackage.vue';
import { dueLabel } from '@/lib/content';
import type { ContentActions, ContentItem, ContentRevision } from '@/types/content';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';

const item = (overrides: Partial<ContentItem> = {}): ContentItem =>
    ({ id: 'item-1', status: 'APPROVED', dueState: 'overdue', plannedAt: '2026-11-09T03:00:00Z', publication: null, ...overrides }) as ContentItem;
const revision = { id: 'rev-1', number: 1, caption: 'Caption', hashtags: '#a', altText: 'Alt', asset: null } as unknown as ContentRevision;
const actions = { markPublished: null } as ContentActions;
const mountPackage = (i: ContentItem = item()) => mount(ContentPostingPackage, { props: { item: i, revision, actions } });
const publishButton = (wrapper: ReturnType<typeof mountPackage>) => wrapper.findAll('button').find((b) => b.text().includes('Tandai sudah dipublikasikan'));

afterEach(() => window.sessionStorage.clear());

describe('dueLabel', () => {
    it('words the reminder', () => {
        expect(dueLabel('overdue', 2)).toBe('Terlambat 2 hari');
        expect(dueLabel('today')).toBe('Jadwal hari ini');
        expect(dueLabel('upcoming')).toBeNull();
        expect(dueLabel(null)).toBeNull();
    });
});

describe('ContentPostingPackage', () => {
    it('reminds that an approved post is overdue', () => {
        expect(mountPackage().get('[data-testid="due-banner"]').text()).toContain('Terlambat');
    });

    it('enables marking published only after every checklist step, and remembers the steps', async () => {
        const wrapper = mountPackage();
        expect(publishButton(wrapper)?.attributes('disabled')).toBeDefined();
        expect(wrapper.get('#reason-checklist').text()).toBe('Centang semua langkah checklist dulu.');

        for (const step of ['image', 'caption', 'alt_text', 'account']) {
            await wrapper.get(`#step-${step}`).trigger('click');
        }
        await flushPromises();

        expect(publishButton(wrapper)?.attributes('disabled')).toBeUndefined();
        expect(JSON.parse(window.sessionStorage.getItem('content-checklist:item-1:rev-1') ?? '[]')).toHaveLength(4);

        const again = mountPackage();
        await flushPromises();
        expect(publishButton(again)?.attributes('disabled')).toBeUndefined();
    });

    it('shows the confirmed checklist as evidence once published', () => {
        const wrapper = mountPackage(
            item({
                status: 'PUBLISHED',
                dueState: null,
                publication: {
                    publishedAt: '2026-11-10T05:00:00Z',
                    url: null,
                    note: null,
                    checklist: ['image', 'caption', 'alt_text', 'account'],
                    actor: 'Admin',
                    revisionId: 'rev-1',
                    revisionNumber: 1,
                    recordedAt: '2026-11-10T05:01:00Z',
                },
            }),
        );

        expect(wrapper.get('[data-testid="publication"]').text()).toContain('Checklist posting dikonfirmasi (4/4)');
        expect(publishButton(wrapper)).toBeUndefined();
    });
});
