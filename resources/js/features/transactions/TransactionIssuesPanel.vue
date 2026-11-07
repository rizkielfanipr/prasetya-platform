<script setup lang="ts">
import PanelCard from '@/components/layout/PanelCard.vue';
import GatewayCheckDialog from '@/features/reconciliation/GatewayCheckDialog.vue';
import { formatDateTime } from '@/lib/format';
import { reconciliationGroups, rejectionLabels } from '@/lib/status';
import type { TransactionIssue } from '@/types/backoffice';
import { Info, TriangleAlert } from '@lucide/vue';

defineProps<{ issues: TransactionIssue[] }>();
</script>

<template>
    <PanelCard
        v-if="issues.length > 0"
        title="Perlu ditinjau"
        description="Temuan rekonsiliasi untuk transaksi ini. Tidak ada yang diperbaiki otomatis."
        data-testid="transaction-issues"
    >
        <ul class="divide-y">
            <li v-for="issue in issues" :key="issue.key" class="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="issue.needsAction ? 'bg-warning-surface text-warning' : 'bg-muted text-muted-foreground'"
                        >
                            <component :is="issue.needsAction ? TriangleAlert : Info" class="size-3.5" aria-hidden="true" />
                            {{ issue.needsAction ? 'Perlu tindakan' : 'Informasi' }}
                        </span>
                        <span class="font-semibold">{{ reconciliationGroups[issue.group].label }}</span>
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ reconciliationGroups[issue.group].description }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ issue.invoice ?? 'Invoice tidak diketahui' }} · <time :datetime="issue.at">{{ formatDateTime(issue.at) }}</time
                        ><template v-if="issue.detail"> · {{ rejectionLabels[issue.detail] ?? issue.detail }}</template>
                    </p>
                </div>
                <GatewayCheckDialog v-if="issue.checkablePaymentId" :payment-id="issue.checkablePaymentId" :invoice="issue.invoice" />
            </li>
        </ul>
    </PanelCard>
</template>
