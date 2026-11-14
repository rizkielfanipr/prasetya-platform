<script setup lang="ts">
import { useItemEvidence } from '@/composables/useItemEvidence';
import ContentAiRuns from '@/features/content/ContentAiRuns.vue';
import ContentRevisionHistory from '@/features/content/ContentRevisionHistory.vue';
import { routes } from '@/lib/routes';
import type { ContentItem, ItemHistory } from '@/types/content';
import DetailSheet from './DetailSheet.vue';

/**
 * Riwayat versi (ADR-0013), loaded when opened: every revision with the Ready marker and legacy approval decisions,
 * AI provenance, the comparison of two revisions, and the item's AI runs with people's verdicts.
 */
const props = defineProps<{ item: ContentItem }>();
const open = defineModel<boolean>('open', { default: false });
const { data, state, error, load } = useItemEvidence<ItemHistory>(() => routes.contentItemHistory(props.item.id), open);
</script>

<template>
    <DetailSheet
        v-model:open="open"
        title="Riwayat versi"
        description="Setiap revisi, penanda siap posting, keputusan alur lama, dan draft AI. Revisi yang sudah dibekukan tidak berubah."
        :state="state"
        :error="error"
        testid="history-sheet"
        @retry="load()"
    >
        <div v-if="data" class="space-y-8">
            <section aria-labelledby="history-revisions" class="space-y-3">
                <h3 id="history-revisions" class="text-base font-bold">Revisi</h3>
                <ContentRevisionHistory :revisions="data.revisions" :ready-revision-id="data.readyRevisionId" :approved-revision-id="data.approvedRevisionId" />
            </section>
            <section aria-labelledby="history-ai" class="space-y-3 border-t pt-6">
                <h3 id="history-ai" class="text-base font-bold">Draft AI</h3>
                <ContentAiRuns :runs="data.runs" />
            </section>
        </div>
    </DetailSheet>
</template>
