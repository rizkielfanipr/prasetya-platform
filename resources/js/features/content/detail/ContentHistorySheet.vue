<script setup lang="ts">
import AuditTimeline from '@/components/shared/AuditTimeline.vue';
import { type EvidenceState, useItemEvidence } from '@/composables/useItemEvidence';
import ContentAiRuns from '@/features/content/ContentAiRuns.vue';
import ContentRevisionHistory from '@/features/content/ContentRevisionHistory.vue';
import { routes } from '@/lib/routes';
import type { ContentItem, ItemActivity, ItemHistory } from '@/types/content';
import { computed } from 'vue';
import DetailSheet from './DetailSheet.vue';

/**
 * Riwayat (ADR-0017): one sheet for the item's story, loaded when opened. Revisions first (the Ready marker, legacy
 * decisions, AI provenance, compare), then the AI drafts, then the item's own audit, newest first.
 */
const props = defineProps<{ item: ContentItem }>();
const open = defineModel<boolean>('open', { default: false });
const history = useItemEvidence<ItemHistory>(() => routes.contentItemHistory(props.item.id), open);
const activity = useItemEvidence<ItemActivity>(() => routes.contentItemActivity(props.item.id), open);

// The sheet waits for both halves; a failure of either is shown once, and retry reloads both.
const state = computed<EvidenceState>(() => {
    const states = [history.state.value, activity.state.value];
    if (states.includes('error')) return 'error';
    if (states.includes('loading') || states.includes('idle')) return 'loading';

    return 'ready';
});
const error = computed(() => history.error.value ?? activity.error.value);

function retry(): void {
    void history.load();
    void activity.load();
}
</script>

<template>
    <DetailSheet
        v-model:open="open"
        title="Riwayat"
        description="Setiap revisi, draft AI, dan catatan audit konten ini. Revisi yang sudah dibekukan tidak berubah."
        :state="state"
        :error="error"
        testid="history-sheet"
        @retry="retry"
    >
        <div v-if="history.data.value && activity.data.value" class="space-y-6">
            <section aria-labelledby="history-revisions" class="space-y-3">
                <h3 id="history-revisions" class="text-sm font-extrabold">Revisi</h3>
                <ContentRevisionHistory
                    :revisions="history.data.value.revisions"
                    :ready-revision-id="history.data.value.readyRevisionId"
                    :approved-revision-id="history.data.value.approvedRevisionId"
                />
            </section>
            <section aria-labelledby="history-ai" class="space-y-3 border-t border-border pt-5">
                <h3 id="history-ai" class="text-sm font-extrabold">Draft AI</h3>
                <ContentAiRuns :runs="history.data.value.runs" />
            </section>
            <section aria-labelledby="history-audit" class="space-y-3 border-t border-border pt-5" data-testid="history-audit">
                <h3 id="history-audit" class="text-sm font-extrabold">Aktivitas</h3>
                <AuditTimeline :entries="activity.data.value.entries" />
            </section>
        </div>
    </DetailSheet>
</template>
