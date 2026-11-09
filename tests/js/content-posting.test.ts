import ContentPostingPackage from '@/features/content/ContentPostingPackage.vue';
import { dueLabel } from '@/lib/content';
import type { ContentActions, ContentItem, ContentRevision } from '@/types/content';
import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';

const item = (overrides: Partial<ContentItem> = {}): ContentItem =>
    ({ id: 'item-1', status: 'READY', dueState: 'overdue', plannedAt: '2026-11-09T03:00:00Z', publication: null, ...overrides }) as ContentItem;
const revision = { id: 'rev-1', number: 1, caption: 'Caption', hashtags: '#a', altText: 'Alt', asset: null } as unknown as ContentRevision;
const actions = { markPublished: null } as ContentActions;
const mountPackage = (i: ContentItem = item()) => mount(ContentPostingPackage, { props: { item: i, revision, actions } });
const publishButton = (wrapper: ReturnType<typeof mountPackage>) => wrapper.findAll('button').find((b) => b.text().includes('Tandai sudah diposting'));

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
    it('reminds that a Ready post is overdue', () => {
        expect(mountPackage().get('[data-testid="due-banner"]').text()).toContain('Terlambat');
    });

    it('lets a Ready post be marked posted straight away, with no checklist', () => {
        const wrapper = mountPackage();

        expect(publishButton(wrapper)?.attributes('disabled')).toBeUndefined();
        expect(wrapper.find('[type="checkbox"], [role="checkbox"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Checklist');
    });

    it('offers the copy buttons the post needs: caption with hashtags, hashtags, and alt text', () => {
        const labels = mountPackage()
            .findAll('button')
            .map((b) => b.text());

        expect(labels).toContain('Salin caption');
        expect(labels).toContain('Salin hashtag');
        expect(labels).toContain('Salin alt text');
        expect(labels).not.toContain('Salin caption + hashtag');
    });

    it('explains why a post cannot be marked yet', () => {
        const wrapper = mount(ContentPostingPackage, {
            props: { item: item(), revision, actions: { markPublished: 'Tandai siap posting dulu sebelum mencatat posting.' } as ContentActions },
        });

        expect(publishButton(wrapper)?.attributes('disabled')).toBeDefined();
        expect(wrapper.get('#reason-markPublished').text()).toBe('Tandai siap posting dulu sebelum mencatat posting.');
    });

    it('still shows a checklist confirmed on an older publication as evidence', () => {
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
