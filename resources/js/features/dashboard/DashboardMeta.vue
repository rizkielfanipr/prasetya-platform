<script setup lang="ts">
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import { formatChange, formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { Brand, MetricPair, SharedProps } from '@/types/backoffice';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Active orders and the Perlu Ditinjau chip. From lg they sit on the scope row (no extra height above the fold);
 * below lg they close the route band. The issue count is global, so it says so when a brand filter is on.
 */
/** `compact` drops the change sentence below 1440 px, where the scope row has room only for the count and the chip. */
const props = defineProps<{ activeOrders: MetricPair; brand: Brand | null; comparison: string; compact?: boolean }>();

const page = usePage<SharedProps>();
const openIssues = computed(() => page.props.openIssueCount ?? 0);
const change = computed(() => {
    const delta = formatChange(props.activeOrders.value, props.activeOrders.previous);
    if (delta.direction === 'flat') return `sama seperti ${props.comparison}`;
    if (props.activeOrders.previous === 0) return `belum ada pada ${props.comparison}`;

    return `${delta.label} dibanding ${props.comparison}`;
});
</script>

<template>
    <div class="flex-wrap items-center gap-x-5 gap-y-2 text-[13px]">
        <!-- Compact: the change sentence (with its dot) shows only from 1440 px; the count alone otherwise. -->
        <span class="text-sidebar-foreground"
            ><span class="whitespace-nowrap"
                ><b class="font-extrabold tabular">{{ formatNumber(activeOrders.value) }}</b> pesanan aktif</span
            ><span :class="{ 'max-[89.99rem]:hidden': compact }"><SeparatorDot />{{ change }}</span></span
        >
        <Link
            v-if="openIssues > 0"
            :href="routes.transactionsAttention"
            class="inline-flex min-h-8 items-center gap-1.5 rounded-md bg-warning-surface px-2.5 font-extrabold text-warning underline-offset-4 hover:underline"
            >Perlu Ditinjau {{ formatNumber(openIssues) }} isu<template v-if="brand"> (semua brand)</template
            ><ArrowRightIcon class="size-3.5 stroke-2" aria-hidden="true"
        /></Link>
        <span v-else class="font-semibold text-muted-foreground">Tidak ada isu yang perlu ditinjau</span>
    </div>
</template>
