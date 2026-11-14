<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import ArchivedContentWorkspace from '@/features/content/detail/ArchivedContentWorkspace.vue';
import ContentActivitySheet from '@/features/content/detail/ContentActivitySheet.vue';
import ContentDetailHeader from '@/features/content/detail/ContentDetailHeader.vue';
import ContentHistorySheet from '@/features/content/detail/ContentHistorySheet.vue';
import ContentLifecycleDialogs from '@/features/content/detail/ContentLifecycleDialogs.vue';
import ContentPlanDialog from '@/features/content/detail/ContentPlanDialog.vue';
import ContentResearchSheet from '@/features/content/detail/ContentResearchSheet.vue';
import DraftContentWorkspace from '@/features/content/detail/DraftContentWorkspace.vue';
import PublishedContentWorkspace from '@/features/content/detail/PublishedContentWorkspace.vue';
import ReadyPostingWorkspace from '@/features/content/detail/ReadyPostingWorkspace.vue';
import { formatDateTime } from '@/lib/format';
import type {
    ArchiveEvidence,
    ContentActions,
    ContentItem,
    ContentLimits,
    ContentRevision,
    DetailSurface,
    InsightSnapshot,
    ItemAi,
    PlanOptions,
    RuleFinding,
} from '@/types/content';
import { computed, ref, watch } from 'vue';

/**
 * One post's workspace, chosen by the server's status (ADR-0013) on one canonical URL: Draft to make it, Ready to post
 * it, Published for its evidence and insight, Archived read-only. Props a status does not need arrive as null. Plan,
 * Detail kreatif, research, history, and activity open by name from Lainnya and load their evidence when opened.
 */
const props = defineProps<{
    item: ContentItem;
    revision: ContentRevision;
    actions: ContentActions;
    limits: ContentLimits | null;
    findings: RuleFinding[] | null;
    brandRulesVersion: number | null;
    planOptions: PlanOptions | null;
    ai: ItemAi | null;
    postedRevision: ContentRevision | null;
    insights: InsightSnapshot[] | null;
    archive: ArchiveEvidence | null;
}>();

const surface = ref<DetailSurface | null>(null);
const surfaceModel = (name: DetailSurface) =>
    computed({
        get: () => surface.value === name,
        set: (open: boolean) => {
            if (open) surface.value = name;
            else if (surface.value === name) surface.value = null;
        },
    });
const planOpen = surfaceModel('plan');
const creativeOpen = surfaceModel('creative');
const researchOpen = surfaceModel('research');
const historyOpen = surfaceModel('history');
const activityOpen = surfaceModel('activity');
const returning = surfaceModel('returnToDraft');
const archiving = surfaceModel('archive');

// "Edit konten" on Ready shows the editor until the save returns the item to Draft, or the person goes back.
const editingReady = ref(false);
watch(
    () => props.item.status,
    () => {
        editingReady.value = false;
        surface.value = null;
    },
);

const description = computed(() => `${props.item.brand.name} · ${formatDateTime(props.item.plannedAt)}`);
const editing = computed(() => props.item.status === 'DRAFT' || (props.item.status === 'READY' && editingReady.value));
</script>

<template>
    <AppLayout :title="item.title" :description="description">
        <!-- One reading width for every status, so the header lines up with the workspace below it. -->
        <div class="max-w-[72rem] space-y-4 md:space-y-5 lg:space-y-6">
            <ContentDetailHeader :item="item" :actions="actions" :editing="editingReady" @open="(name) => (surface = name)" />

            <DraftContentWorkspace
                v-if="editing && limits && findings"
                v-model:creative-open="creativeOpen"
                :item="item"
                :revision="revision"
                :actions="actions"
                :limits="limits"
                :findings="findings"
                :brand-rules-version="brandRulesVersion"
                :ai="ai"
                :editing-ready="item.status === 'READY'"
                @cancel-edit="editingReady = false"
            />
            <ReadyPostingWorkspace
                v-else-if="item.status === 'READY'"
                :item="item"
                :revision="revision"
                :actions="actions"
                :findings="findings ?? []"
                :brand-rules-version="brandRulesVersion"
                @edit="editingReady = true"
            />
            <PublishedContentWorkspace
                v-else-if="item.status === 'PUBLISHED' && item.publication && postedRevision"
                :item="item"
                :publication="item.publication"
                :posted-revision="postedRevision"
                :insights="insights ?? []"
            />
            <ArchivedContentWorkspace v-else :item="item" :revision="revision" :archive="archive" @open="(name) => (surface = name)" />

            <ContentPlanDialog v-if="planOptions" v-model:open="planOpen" :item="item" :options="planOptions" />
            <ContentLifecycleDialogs v-model:returning="returning" v-model:archiving="archiving" :item="item" :revision="revision" />
            <ContentResearchSheet v-model:open="researchOpen" :item="item" />
            <ContentHistorySheet v-model:open="historyOpen" :item="item" />
            <ContentActivitySheet v-model:open="activityOpen" :item="item" />
        </div>
    </AppLayout>
</template>
