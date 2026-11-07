import ContentAiPanel from '@/features/content/ContentAiPanel.vue';
import { formatUsd } from '@/lib/content';
import type { ContentItem, ContentRevision, GenerationRun, ItemAi } from '@/types/content';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const inertia = vi.hoisted(() => ({ reload: vi.fn(), post: vi.fn() }));
vi.mock('@inertiajs/vue3', async (original) => ({ ...(await original<object>()), router: inertia }));

const item = { id: 'item-1', status: 'DRAFT' } as ContentItem;
const revision = { id: '01REVISION000000000000000A' } as ContentRevision;
const run = (overrides: Partial<GenerationRun> = {}): GenerationRun => ({
    id: 'r1',
    status: 'SUCCEEDED',
    fields: ['caption', 'hashtags'],
    instruction: null,
    usedReviewNote: false,
    feedback: null,
    feedbackReason: null,
    provider: 'openai_compatible',
    model: 'deepseek-test',
    promptVersion: 'copy-v1',
    inputTokens: 1200,
    outputTokens: 300,
    costUsd: 0.0012,
    latencyMs: 900,
    attempts: 1,
    errorCode: null,
    errorMessage: null,
    requestedBy: 'Admin',
    resultRevisionNumber: 2,
    item: { id: 'item-1', title: 'Judul' },
    createdAt: '2026-11-08T03:00:00Z',
    finishedAt: '2026-11-08T03:00:02Z',
    ...overrides,
});
const mountPanel = (ai: ItemAi) => mount(ContentAiPanel, { props: { item, revision, ai } });
const aiButton = (wrapper: ReturnType<typeof mountPanel>) => {
    const button = wrapper.findAll('button').find((b) => b.text().includes('Buat draft dengan AI'));
    if (!button) throw new Error('AI button not found');
    return button;
};

beforeEach(() => vi.useFakeTimers());
afterEach(() => {
    vi.useRealTimers();
    vi.clearAllMocks();
});

describe('formatUsd', () => {
    it('keeps small AI costs readable', () => {
        expect(formatUsd(0.0012)).toBe('$0.00120');
        expect(formatUsd(4.5)).toBe('$4.50');
        expect(formatUsd(0)).toBe('$0.00');
    });
});

describe('ContentAiPanel', () => {
    it('keeps the button visible but disabled, with the reason', () => {
        const wrapper = mountPanel({ unavailableReason: 'AI belum dikonfigurasi. Konten tetap dapat ditulis manual.', reviewRequest: null, runs: [] });

        const button = aiButton(wrapper);
        expect(button.attributes('disabled')).toBeDefined();
        expect(button.attributes('aria-describedby')).toBe('ai-unavailable');
        expect(wrapper.get('#ai-unavailable').text()).toContain('AI belum dikonfigurasi');
    });

    it('asks for the chosen fields of the current revision', async () => {
        const wrapper = mountPanel({ unavailableReason: null, reviewRequest: null, runs: [] });

        await aiButton(wrapper).trigger('click');

        expect(inertia.post).toHaveBeenCalledWith(
            '/content/items/item-1/generate',
            { revision: revision.id, fields: ['caption', 'hashtags', 'alt_text', 'visual_direction'], instruction: null },
            expect.anything(),
        );
    });

    it('lists runs with outcome, usage, and cost in words', () => {
        const wrapper = mountPanel({
            unavailableReason: null,
            reviewRequest: null,
            runs: [run(), run({ id: 'r2', status: 'FAILED', errorMessage: 'Provider AI sedang bermasalah (503).', resultRevisionNumber: null })],
        });

        expect(wrapper.get('[data-testid="ai-run-SUCCEEDED"]').text()).toContain('Caption, Hashtag');
        expect(wrapper.get('[data-testid="ai-run-SUCCEEDED"]').text()).toContain('revisi 2 · 1500 token · $0.00120');
        expect(wrapper.get('[data-testid="ai-run-FAILED"]').text()).toContain('Provider AI sedang bermasalah (503).');
    });

    it('refreshes while a run is active and stops when it finishes', async () => {
        const wrapper = mountPanel({ unavailableReason: 'AI sedang membuat draft untuk konten ini.', reviewRequest: null, runs: [run({ status: 'RUNNING' })] });

        vi.advanceTimersByTime(3000);
        expect(inertia.reload).toHaveBeenCalledTimes(1);

        await wrapper.setProps({ ai: { unavailableReason: null, reviewRequest: null, runs: [run()] } });
        vi.advanceTimersByTime(9000);
        expect(inertia.reload).toHaveBeenCalledTimes(1);
        expect(wrapper.get('[aria-live="polite"]').text()).toBe('Draft dibuat');
    });
});

describe('ContentAiPanel answering a revision request', () => {
    it("preselects the copy parts the reviewer named and sends the writer's instruction", async () => {
        const wrapper = mountPanel({
            unavailableReason: null,
            reviewRequest: { areas: ['caption', 'image'], note: 'Ringkas caption', actor: 'Peninjau', at: '2026-11-08T03:00:00Z' },
            runs: [],
        });

        expect(wrapper.get('[data-testid="ai-follows-review"]').text()).toContain('catatan peninjau');
        await wrapper.get('#ai-instruction').setValue('lebih singkat');
        await aiButton(wrapper).trigger('click');

        expect(inertia.post).toHaveBeenCalledWith(
            '/content/items/item-1/generate',
            { revision: revision.id, fields: ['caption'], instruction: 'lebih singkat' },
            expect.anything(),
        );
    });

    it('offers a verdict on each finished draft', () => {
        const wrapper = mountPanel({ unavailableReason: null, reviewRequest: null, runs: [run({ feedback: 'NOT_USEFUL', feedbackReason: 'Terlalu formal' })] });

        expect(wrapper.text()).toContain('Dinilai kurang berguna: Terlalu formal');
        expect(wrapper.findAll('button').some((b) => b.text().includes('Berguna'))).toBe(true);
    });
});
