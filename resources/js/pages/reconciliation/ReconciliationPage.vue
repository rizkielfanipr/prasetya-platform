<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import AttentionView from '@/features/reconciliation/AttentionView.vue';
import { routes } from '@/lib/routes';
import type { Paginated, ReconciliationGroup, ReconciliationIssue } from '@/types/backoffice';

// The old Rekonsiliasi page, kept during the ADR-0010 transition. The same view lives at Transaksi → Perlu Ditinjau.
defineProps<{
    group: ReconciliationGroup;
    counts: Record<ReconciliationGroup, number>;
    needsAction: ReconciliationGroup[];
    thresholds: { pending_too_long_minutes: number; no_attempt_after_minutes: number };
    issues: Paginated<ReconciliationIssue>;
}>();
</script>

<template>
    <AppLayout title="Rekonsiliasi" description="Temukan ketidakcocokan. Halaman ini hanya mengamati dan tidak memperbaiki data secara otomatis.">
        <AttentionView :group="group" :counts="counts" :needs-action="needsAction" :thresholds="thresholds" :issues="issues" :path="routes.reconciliation" />
    </AppLayout>
</template>
