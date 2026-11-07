<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import AuditTimeline from '@/components/shared/AuditTimeline.vue';
import ContentAiPanel from '@/features/content/ContentAiPanel.vue';
import ContentDetailsDialog from '@/features/content/ContentDetailsDialog.vue';
import ContentEditor from '@/features/content/ContentEditor.vue';
import ContentPostingPackage from '@/features/content/ContentPostingPackage.vue';
import ContentReviewPanel from '@/features/content/ContentReviewPanel.vue';
import ContentRuleFindings from '@/features/content/ContentRuleFindings.vue';
import ContentRevisionHistory from '@/features/content/ContentRevisionHistory.vue';
import ContentStatusBadge from '@/features/content/ContentStatusBadge.vue';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { AuditEntry } from '@/types/backoffice';
import type { ContentActions, ContentItem, ContentLimits, ContentRevision, ItemAi, PlanOptions, RuleFinding } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';

/** One post's workspace: content, review, posting package, history, and its own audit (ADR-0011). */
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
}>();

const postable = computed(() => props.item.status === 'APPROVED' || props.item.status === 'PUBLISHED');
const calendarHref = computed(() => `${routes.contentCalendar}?week=${props.item.plannedDay}`);
</script>

<template>
    <AppLayout :title="item.title" :description="`${item.brand.name} · rencana ${formatDateTime(item.plannedAt)}`">
        <Link :href="calendarHref" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-text hover:underline">
            <ArrowLeft class="size-4" aria-hidden="true" />Kembali ke kalender
        </Link>

        <PanelCard title="Ringkasan">
            <template #actions><ContentDetailsDialog :item="item" :options="planOptions" :blocked-reason="actions.editDetails" /></template>
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Status</dt>
                    <dd class="mt-1"><ContentStatusBadge :status="item.status" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Brand</dt>
                    <dd class="mt-1">{{ item.brand.name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Rencana posting</dt>
                    <dd class="mt-1">{{ formatDateTime(item.plannedAt) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Pilar · kampanye</dt>
                    <dd class="mt-1">{{ item.pillar?.name ?? 'Tanpa pilar' }} · {{ item.campaign?.name ?? 'tanpa kampanye' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Topik</dt>
                    <dd class="mt-1">{{ item.topic ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Revisi aktif</dt>
                    <dd class="mt-1">{{ revision.number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Dibuat</dt>
                    <dd class="mt-1">{{ item.createdBy }} · {{ formatDateTime(item.createdAt) }}</dd>
                </div>
            </dl>
        </PanelCard>

        <PanelCard title="Tinjauan"><ContentReviewPanel :item="item" :revision="revision" :actions="actions" :review-request="ai.reviewRequest" /></PanelCard>

        <PanelCard title="Aturan brand" description="Pemeriksaan otomatis tanpa AI terhadap aturan, fakta, pilar, dan kampanye brand.">
            <ContentRuleFindings :findings="findings" :version="brandRulesVersion" :submitted-version="revision.brandRulesVersion" />
        </PanelCard>

        <PanelCard v-if="postable" title="Paket posting" description="Posting manual di Instagram dari revisi yang disetujui, lalu catat di sini.">
            <ContentPostingPackage :item="item" :revision="revision" :actions="actions" />
        </PanelCard>

        <PanelCard
            title="Asisten AI"
            description="Draft caption, hashtag, alt text, dan arahan visual dari brief dan aturan brand. Gambar tetap diunggah manual."
        >
            <ContentAiPanel :item="item" :revision="revision" :ai="ai" />
        </PanelCard>

        <PanelCard :title="`Konten · revisi ${revision.number}`" description="Kolom bertanda * wajib diisi sebelum diajukan.">
            <ContentEditor :key="revision.id" :item="item" :revision="revision" :blocked-reason="actions.edit" :limits="limits" />
        </PanelCard>

        <div class="grid gap-4 md:gap-5 lg:grid-cols-2 lg:gap-6">
            <PanelCard title="Riwayat revisi"><ContentRevisionHistory :revisions="revisions" :approved-revision-id="item.approvedRevisionId" /></PanelCard>
            <PanelCard title="Aktivitas" description="Audit konten ini, terbaru di atas (maksimal 100 catatan)."
                ><AuditTimeline :entries="activity"
            /></PanelCard>
        </div>
    </AppLayout>
</template>
