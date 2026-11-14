import ContentResearchPanel from '@/features/content/ContentResearchPanel.vue';
import type { ContentItem, ItemResearch, ResearchSource } from '@/types/content';
import { mount, RouterLinkStub } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';

const inertia = vi.hoisted(() => ({ reload: vi.fn(), post: vi.fn(), delete: vi.fn() }));
vi.mock('@inertiajs/vue3', async (original) => ({ ...(await original<object>()), router: inertia }));

const source = (overrides: Partial<ResearchSource> = {}): ResearchSource => ({
    id: 's1',
    url: 'https://www.bps.go.id/umkm',
    domain: 'bps.go.id',
    title: 'Statistik UMKM',
    summary: 'Ringkasan resmi.',
    trust: 'OFFICIAL',
    origin: 'SEARCH',
    relevance: 0.9,
    publishedOn: null,
    fetchedAt: '2026-11-08T03:00:00Z',
    note: null,
    ...overrides,
});
const research = (overrides: Partial<ItemResearch> = {}): ItemResearch => ({
    unavailableReason: null,
    cited: [],
    runs: [],
    internal: { campaigns: [], facts: 3, similar: [] },
    suggestedQuery: 'website umkm',
    ...overrides,
});
const item = { id: 'item-1', status: 'DRAFT' } as ContentItem;
const mountPanel = (r: ItemResearch, editable = true) =>
    mount(ContentResearchPanel, { props: { item, research: r, editable }, global: { stubs: { Link: RouterLinkStub } } });

afterEach(() => vi.clearAllMocks());

describe('ContentResearchPanel', () => {
    it('shows internal context first, including similar content to avoid repeating', () => {
        const wrapper = mountPanel(
            research({
                internal: {
                    campaigns: [{ name: 'November', objective: null }],
                    facts: 4,
                    similar: [{ id: 'x', title: 'Tips SEO UMKM', status: 'PUBLISHED', plannedAt: '2026-11-01T03:00:00Z', score: 0.6 }],
                },
            }),
        );

        expect(wrapper.text()).toContain('4 knowledge berlaku');
        expect(wrapper.text()).toContain('campaign berjalan: November');
        expect(wrapper.get('[data-testid="similar-content"]').text()).toContain('Tips SEO UMKM');
        expect(wrapper.get('[data-testid="similar-content"]').text()).toContain('Sudah diposting');
    });

    it('links sources with no referrer or follow and labels their trust in words', () => {
        const wrapper = mountPanel(research({ cited: [source({ trust: 'USER_GENERATED', note: 'Poin kedua' })] }));

        const link = wrapper.get('[data-testid="cited-sources"] a');
        expect(link.attributes('rel')).toBe('noopener noreferrer nofollow');
        expect(link.attributes('target')).toBe('_blank');
        expect(wrapper.get('[data-testid="cited-sources"]').text()).toContain('Konten pengguna');
        expect(wrapper.get('[data-testid="cited-sources"]').text()).toContain('Catatan tim: Poin kedua');
    });

    it('keeps search visible but disabled with the reason when it cannot run', () => {
        const wrapper = mountPanel(research({ unavailableReason: 'Pencarian web belum dikonfigurasi. Sumber tetap dapat ditambahkan manual.' }));

        const button = wrapper.findAll('button').find((b) => b.text().includes('Cari di web'));
        expect(button?.attributes('disabled')).toBeDefined();
        expect(wrapper.get('#research-unavailable').text()).toContain('Sumber tetap dapat ditambahkan manual');
    });

    it('offers each result once and explains dropped results', async () => {
        const wrapper = mountPanel(
            research({
                cited: [source()],
                runs: [
                    {
                        id: 'r1',
                        query: 'umkm',
                        status: 'SUCCEEDED',
                        provider: 'fake',
                        resultsCount: 2,
                        droppedCount: 1,
                        costUsd: 0,
                        errorMessage: null,
                        requestedBy: 'Admin',
                        createdAt: '2026-11-08T03:00:00Z',
                        sources: [source(), source({ id: 's2', title: 'Tren', trust: 'GENERAL', domain: 'contoh.id', url: 'https://contoh.id/a' })],
                    },
                ],
            }),
        );

        const buttons = wrapper.findAll('[data-testid="research-run-SUCCEEDED"] button');
        expect(buttons.map((b) => b.text())).toEqual(['Dipakai', 'Pakai']);
        expect(buttons[0]?.attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain('1 hasil dibuang karena alamat atau domainnya tidak aman.');

        await buttons[1]?.trigger('click');
        expect(inertia.post).toHaveBeenCalledWith('/content/items/item-1/sources/s2', {}, expect.anything());
    });

    it('is read-only outside Draft: sources stay readable, nothing can change', () => {
        const wrapper = mountPanel(research({ cited: [source()] }), false);

        expect(wrapper.findAll('button')).toHaveLength(0);
        expect(wrapper.find('#research-query').exists()).toBe(false);
        expect(wrapper.get('[data-testid="cited-sources"]').text()).toContain('Statistik UMKM');
        expect(wrapper.text()).toContain('Tidak ada riset web untuk konten ini.');
    });
});
