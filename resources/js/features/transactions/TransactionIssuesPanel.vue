<script setup lang="ts">
import GatewayCheckDialog from '@/features/reconciliation/GatewayCheckDialog.vue';
import { formatDateTime } from '@/lib/format';
import { reconciliationGroups, rejectionLabels } from '@/lib/status';
import type { TransactionIssue } from '@/types/backoffice';
import { ExclamationTriangleIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

/**
 * The transaction's reconciliation findings at the top of its page (ADR-0016): what needs a decision on a warning
 * field, information on a tint field below it. Nothing here repairs a record.
 */
const props = defineProps<{ issues: TransactionIssue[] }>();

const blocks = computed(() =>
    [
        {
            key: 'action',
            title: 'Perlu ditinjau',
            tag: 'Perlu tindakan',
            icon: ExclamationTriangleIcon,
            items: props.issues.filter((issue) => issue.needsAction),
        },
        { key: 'info', title: 'Informasi', tag: 'Informasi', icon: InformationCircleIcon, items: props.issues.filter((issue) => !issue.needsAction) },
    ].filter((block) => block.items.length > 0),
);
</script>

<template>
    <div v-if="issues.length > 0" class="space-y-3" data-testid="transaction-issues">
        <section
            v-for="block in blocks"
            :key="block.key"
            :aria-labelledby="`issues-${block.key}`"
            class="rounded-xl px-5 py-4"
            :class="block.key === 'action' ? 'bg-warning-surface' : 'bg-tint-50'"
        >
            <h2
                :id="`issues-${block.key}`"
                class="flex items-center gap-2 text-sm font-extrabold"
                :class="block.key === 'action' ? 'text-warning' : 'text-foreground'"
            >
                <component :is="block.icon" class="size-4 stroke-2" aria-hidden="true" />{{ block.title }}
            </h2>
            <ul class="mt-2 divide-y" :class="block.key === 'action' ? 'divide-warning/20' : 'divide-tint-200'">
                <li v-for="issue in block.items" :key="issue.key" class="flex flex-col gap-2 py-2.5 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <p class="text-sm font-bold">
                            <span class="sr-only">{{ block.tag }}: </span>{{ reconciliationGroups[issue.group].label }}
                        </p>
                        <p class="text-[13px] text-foreground">{{ reconciliationGroups[issue.group].description }}</p>
                        <p class="mt-0.5 text-xs text-foreground tabular">
                            <time :datetime="issue.at">{{ formatDateTime(issue.at) }}</time
                            ><template v-if="issue.detail">, {{ rejectionLabels[issue.detail] ?? issue.detail }}</template>
                        </p>
                    </div>
                    <!-- Information never repeats Cek status: the attempt below already offers it. -->
                    <GatewayCheckDialog
                        v-if="block.key === 'action' && issue.checkablePaymentId"
                        :payment-id="issue.checkablePaymentId"
                        :invoice="issue.invoice"
                    />
                </li>
            </ul>
        </section>
    </div>
</template>
