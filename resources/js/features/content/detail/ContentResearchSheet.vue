<script setup lang="ts">
import { useItemEvidence } from '@/composables/useItemEvidence';
import ContentResearchPanel from '@/features/content/ContentResearchPanel.vue';
import { routes } from '@/lib/routes';
import type { ContentItem, ItemResearch } from '@/types/content';
import { computed, onBeforeUnmount, watch } from 'vue';
import DetailSheet from './DetailSheet.vue';

/**
 * Riset & sumber (ADR-0013), loaded when opened. Sources and searches can change only while the item is a Draft; on
 * other statuses the sheet is read-only. While a search runs and the sheet is open it refreshes every few seconds.
 */
const props = defineProps<{ item: ContentItem }>();
const open = defineModel<boolean>('open', { default: false });

const POLL_MS = 3000;
const { data, state, error, load } = useItemEvidence<ItemResearch>(() => routes.contentResearch(props.item.id), open);
const editable = computed(() => props.item.status === 'DRAFT');
const searching = computed(() => data.value?.runs.some((run) => run.status === 'QUEUED' || run.status === 'RUNNING') ?? false);

let timer: number | null = null;
function stopPolling(): void {
    if (timer !== null) window.clearInterval(timer);
    timer = null;
}
watch(
    () => open.value && searching.value,
    (poll) => {
        stopPolling();
        if (poll) timer = window.setInterval(() => void load({ quiet: true }), POLL_MS);
    },
);
onBeforeUnmount(stopPolling);

const description = computed(() =>
    editable.value
        ? 'Data internal dulu, lalu sumber dari web. Sumber adalah bahan, bukan fakta bisnis.'
        : 'Hanya dapat dilihat. Sumber dan riset web hanya dapat diubah selama konten masih Draft.',
);
</script>

<template>
    <DetailSheet v-model:open="open" title="Riset & sumber" :description="description" :state="state" :error="error" testid="research-sheet" @retry="load()">
        <ContentResearchPanel v-if="data" :item="item" :research="data" :editable="editable" />
    </DetailSheet>
</template>
