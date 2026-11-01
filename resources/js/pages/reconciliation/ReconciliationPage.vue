<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import TablePagination from '@/components/shared/TablePagination.vue';
import { useUrlFilters } from '@/composables/useUrlFilters';
import IssueTable from '@/features/reconciliation/IssueTable.vue';
import { formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import { reconciliationGroups } from '@/lib/status';
import type { Paginated, ReconciliationGroup, ReconciliationIssue } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    group: ReconciliationGroup;
    counts: Record<ReconciliationGroup, number>;
    thresholds: { pending_too_long_minutes: number; no_attempt_after_minutes: number };
    issues: Paginated<ReconciliationIssue>;
}>();

const { apply, loading, failed } = useUrlFilters(
    () => routes.reconciliation,
    () => ({ group: props.group }),
    ['issues', 'counts', 'group'],
);

const groups = Object.keys(reconciliationGroups) as ReconciliationGroup[];
const current = computed(() => reconciliationGroups[props.group]);
</script>

<template>
    <AppLayout title="Rekonsiliasi" description="Temukan ketidakcocokan. Halaman ini hanya mengamati dan tidak memperbaiki data secara otomatis.">
        <nav aria-label="Kelompok isu" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="key in groups"
                :key="key"
                :href="`${routes.reconciliation}?group=${key}`"
                preserve-scroll
                class="flex items-center justify-between gap-3 rounded-md border bg-card px-4 py-3 hover:bg-secondary"
                :class="key === group ? 'border-primary-strong ring-1 ring-primary-strong' : ''"
                :aria-current="key === group ? 'true' : undefined"
                :data-testid="`group-${key}`"
            >
                <span class="font-medium">{{ reconciliationGroups[key].label }}</span>
                <span
                    class="rounded-full px-2.5 py-0.5 text-xs font-bold tabular"
                    :class="counts[key] > 0 ? 'bg-warning-surface text-warning' : 'bg-muted text-muted-foreground'"
                    >{{ formatNumber(counts[key]) }}</span
                >
            </Link>
        </nav>

        <PanelCard :title="current.label" :description="current.description" flush>
            <IssueTable :rows="issues.data" :loading="loading" :failed="failed" @retry="apply({})" />
            <TablePagination :meta="issues.meta" :disabled="loading" @change="apply({ page: String($event) })" />
        </PanelCard>
    </AppLayout>
</template>
