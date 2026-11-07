<script setup lang="ts">
import PanelCard from '@/components/layout/PanelCard.vue';
import TablePagination from '@/components/shared/TablePagination.vue';
import { useUrlFilters } from '@/composables/useUrlFilters';
import IssueTable from '@/features/reconciliation/IssueTable.vue';
import { formatNumber } from '@/lib/format';
import { reconciliationGroups } from '@/lib/status';
import type { Paginated, ReconciliationGroup, ReconciliationIssue } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Transaksi → Perlu Ditinjau (formerly Rekonsiliasi). It only observes: nothing here repairs a record. The groups and
 * their rules come from ReconciliationReport on the server.
 */
const props = withDefaults(
    defineProps<{
        group: ReconciliationGroup;
        counts: Record<ReconciliationGroup, number>;
        needsAction: ReconciliationGroup[];
        thresholds: { pending_too_long_minutes: number; no_attempt_after_minutes: number };
        issues: Paginated<ReconciliationIssue>;
        path: string;
        baseQuery?: Record<string, string>;
    }>(),
    { baseQuery: () => ({}) },
);

const { apply, loading, failed } = useUrlFilters(
    () => props.path,
    () => ({ ...props.baseQuery, group: props.group }),
    ['issues', 'counts', 'group'],
);

const groups = Object.keys(reconciliationGroups) as ReconciliationGroup[];
const current = computed(() => reconciliationGroups[props.group]);
const totalNeedingAction = computed(() => props.needsAction.reduce((sum, key) => sum + props.counts[key], 0));

/** Which threshold a group uses, written out so the operator knows why an item is listed. */
const thresholdNote = computed(() => {
    if (props.group === 'orders_without_attempt') return `Ambang: ${props.thresholds.no_attempt_after_minutes} menit setelah pesanan dibuat.`;
    if (props.group === 'orders_without_success' || props.group === 'pending_too_long') {
        return `Ambang: ${props.thresholds.pending_too_long_minutes} menit.`;
    }

    return null;
});

const href = (key: ReconciliationGroup): string => `${props.path}?${new URLSearchParams({ ...props.baseQuery, group: key }).toString()}`;
</script>

<template>
    <p class="text-sm text-muted-foreground" data-testid="attention-total">
        <span class="font-semibold text-foreground tabular">{{ formatNumber(totalNeedingAction) }}</span> isu perlu tindakan. Halaman ini hanya mengamati dan
        tidak memperbaiki data secara otomatis.
    </p>
    <nav aria-label="Kelompok isu" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <Link
            v-for="key in groups"
            :key="key"
            :href="href(key)"
            preserve-scroll
            class="flex items-center justify-between gap-3 rounded-md border bg-card px-4 py-3 hover:bg-secondary"
            :class="key === group ? 'border-primary-strong ring-1 ring-primary-strong' : ''"
            :aria-current="key === group ? 'true' : undefined"
            :data-testid="`group-${key}`"
        >
            <span class="min-w-0">
                <span class="block font-medium">{{ reconciliationGroups[key].label }}</span>
                <span v-if="!needsAction.includes(key)" class="block text-xs text-muted-foreground">Informasi, tidak dihitung</span>
            </span>
            <span
                class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold tabular"
                :class="counts[key] > 0 && needsAction.includes(key) ? 'bg-warning-surface text-warning' : 'bg-muted text-muted-foreground'"
                >{{ formatNumber(counts[key]) }}</span
            >
        </Link>
    </nav>

    <PanelCard :title="current.label" :description="thresholdNote ? `${current.description} ${thresholdNote}` : current.description" flush>
        <IssueTable :rows="issues.data" :loading="loading" :failed="failed" @retry="apply({})" />
        <TablePagination :meta="issues.meta" :disabled="loading" @change="apply({ page: String($event) })" />
    </PanelCard>
</template>
