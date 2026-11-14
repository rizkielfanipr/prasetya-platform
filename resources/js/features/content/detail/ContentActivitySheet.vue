<script setup lang="ts">
import AuditTimeline from '@/components/shared/AuditTimeline.vue';
import { useItemEvidence } from '@/composables/useItemEvidence';
import { routes } from '@/lib/routes';
import type { ContentItem, ItemActivity } from '@/types/content';
import DetailSheet from './DetailSheet.vue';

/** Aktivitas (ADR-0013), loaded when opened: this item's audit only, newest first, at most 100 entries, redacted. */
const props = defineProps<{ item: ContentItem }>();
const open = defineModel<boolean>('open', { default: false });
const { data, state, error, load } = useItemEvidence<ItemActivity>(() => routes.contentItemActivity(props.item.id), open);
</script>

<template>
    <DetailSheet
        v-model:open="open"
        title="Aktivitas"
        description="Audit konten ini, terbaru di atas (maksimal 100 catatan)."
        :state="state"
        :error="error"
        testid="activity-sheet"
        @retry="load()"
    >
        <AuditTimeline v-if="data" :entries="data.entries" />
    </DetailSheet>
</template>
