<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import AuditTimeline from '@/components/shared/AuditTimeline.vue';
import ContentAiRuns from '@/features/content/ContentAiRuns.vue';
import ContentDetailsDialog from '@/features/content/ContentDetailsDialog.vue';
import ContentInsightPanel from '@/features/content/ContentInsightPanel.vue';
import ContentEditor from '@/features/content/ContentEditor.vue';
import ContentPostingPackage from '@/features/content/ContentPostingPackage.vue';
import ContentResearchPanel from '@/features/content/ContentResearchPanel.vue';
import ContentRevisionHistory from '@/features/content/ContentRevisionHistory.vue';
import ContentStatusBadge from '@/features/content/ContentStatusBadge.vue';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { AuditEntry } from '@/types/backoffice';
import type {
    ContentActions,
    ContentItem,
    ContentLimits,
    ContentRevision,
    InsightSnapshot,
    ItemAi,
    ItemResearch,
    PlanOptions,
    RuleFinding,
} from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';

/**
 * One post's workspace (ADR-0012, phase 3): schedule and status, the posting package when it is Ready, and the editor.
 * Plan details, research, AI runs, revision history, and the item's own audit sit under Pengaturan tambahan.
 */
const props = defineProps<{
    item: ContentItem;
    revision: ContentRevision;
    revisions: ContentRevision[];
    actions: ContentActions;
    activity: AuditEntry[];
    limits: ContentLimits;
    findings: RuleFinding[];
    brandRulesVersion: number | null;
    planOptions: PlanOptions;
    ai: ItemAi;
    research: ItemResearch;
    insights: InsightSnapshot[] | null;
}>();

const postable = computed(() => props.item.status === 'READY' || props.item.status === 'PUBLISHED');
const calendarHref = computed(() => `${routes.contentCalendar}?week=${props.item.plannedDay}`);
</script>

<template>
    <AppLayout :title="item.title" :description="`${item.brand.name} · rencana ${formatDateTime(item.plannedAt)}`">
        <Link :href="calendarHref" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-text hover:underline">
            <ArrowLeft class="size-4" aria-hidden="true" />Kembali ke kalender
        </Link>

        <PanelCard title="Jadwal dan status">
            <template #actions><ContentDetailsDialog :item="item" :options="planOptions" :blocked-reason="actions.editDetails" /></template>
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Status</dt>
                    <dd class="mt-1"><ContentStatusBadge :status="item.status" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Rencana posting</dt>
                    <dd class="mt-1">{{ formatDateTime(item.plannedAt) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Campaign</dt>
                    <dd class="mt-1">{{ item.campaign?.name ?? 'Tanpa campaign' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Revisi aktif</dt>
                    <dd class="mt-1">{{ revision.number }}</dd>
                </div>
            </dl>
        </PanelCard>

        <PanelCard v-if="insights" id="insight" title="Insight" description="Angka dari Insight Instagram, dicatat manual.">
            <ContentInsightPanel :item-id="item.id" :insights="insights" />
        </PanelCard>

        <PanelCard v-if="postable" title="Paket posting" description="Posting manual di Instagram dari revisi yang siap posting, lalu catat di sini.">
            <ContentPostingPackage :item="item" :revision="revision" :actions="actions" />
        </PanelCard>

        <PanelCard :title="`Konten · revisi ${revision.number}`">
            <ContentEditor
                :item="item"
                :revision="revision"
                :blocked-reason="actions.edit"
                :limits="limits"
                :findings="findings"
                :brand-rules-version="brandRulesVersion"
                :ai="ai"
                :actions="actions"
            >
                <template #more>
                    <section aria-labelledby="more-plan" class="space-y-2">
                        <h3 id="more-plan" class="text-sm font-semibold">Rencana</h3>
                        <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <dt class="text-xs font-semibold text-muted-foreground">Judul kerja</dt>
                                <dd class="mt-1 break-words">{{ item.title }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-muted-foreground">Pilar</dt>
                                <dd class="mt-1">{{ item.pillar?.name ?? 'Tanpa pilar' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-muted-foreground">Topik</dt>
                                <dd class="mt-1">{{ item.topic ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold text-muted-foreground">Brand · dibuat</dt>
                                <dd class="mt-1">{{ item.brand.name }} · {{ item.createdBy }}, {{ formatDateTime(item.createdAt) }}</dd>
                            </div>
                        </dl>
                        <p class="text-xs text-muted-foreground">Ubah lewat <span class="font-semibold">Ubah rencana</span> di atas.</p>
                    </section>
                    <section aria-labelledby="more-research" class="space-y-2">
                        <h3 id="more-research" class="text-sm font-semibold">Riset &amp; sumber</h3>
                        <p class="text-xs text-muted-foreground">Data internal dulu, lalu sumber dari web. Sumber adalah bahan, bukan fakta bisnis.</p>
                        <ContentResearchPanel :item="item" :research="research" />
                    </section>
                    <section aria-labelledby="more-ai" class="space-y-2">
                        <h3 id="more-ai" class="text-sm font-semibold">Riwayat draft AI</h3>
                        <ContentAiRuns :runs="ai.runs" />
                    </section>
                    <div class="grid gap-6 lg:grid-cols-2">
                        <section aria-labelledby="more-revisions" class="space-y-2">
                            <h3 id="more-revisions" class="text-sm font-semibold">Riwayat revisi</h3>
                            <ContentRevisionHistory
                                :revisions="revisions"
                                :ready-revision-id="item.readyRevisionId"
                                :approved-revision-id="item.approvedRevisionId"
                            />
                        </section>
                        <section aria-labelledby="more-activity" class="space-y-2">
                            <h3 id="more-activity" class="text-sm font-semibold">Aktivitas</h3>
                            <p class="text-xs text-muted-foreground">Audit konten ini, terbaru di atas (maksimal 100 catatan).</p>
                            <AuditTimeline :entries="activity" />
                        </section>
                    </div>
                </template>
            </ContentEditor>
        </PanelCard>
    </AppLayout>
</template>
