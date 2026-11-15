<script setup lang="ts">
import EmptyState from '@/components/shared/EmptyState.vue';
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import { activitySourceLabels, describeActivity } from '@/lib/activity';
import { formatDate, formatTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { TransactionActivityEntry } from '@/types/backoffice';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * The transaction's one Riwayat (ADR-0016): order status, payment events, webhooks, notes, and audit, newest first
 * from the server. A webhook links to the Log Gateway for its sanitised diagnostics. No line joins the entries: the
 * drawn line belongs to the payment route alone (DESIGN.md).
 */
const props = defineProps<{ entries: TransactionActivityEntry[] }>();

const items = computed(() => props.entries.map((entry) => ({ entry, ...describeActivity(entry) })));
const invoiceOf = (entry: TransactionActivityEntry): string | undefined => (typeof entry.data.invoice === 'string' ? entry.data.invoice : undefined);
</script>

<template>
    <EmptyState v-if="items.length === 0" title="Belum ada aktivitas" description="Aktivitas pesanan, pembayaran, dan audit akan muncul di sini." />
    <ol v-else class="divide-y divide-border" aria-label="Riwayat transaksi">
        <li
            v-for="item in items"
            :key="item.entry.id"
            class="grid gap-x-5 gap-y-0.5 py-3 first:pt-1 sm:grid-cols-[9.5rem_minmax(0,1fr)]"
            :data-source="item.entry.source"
        >
            <p class="text-xs whitespace-nowrap text-muted-foreground tabular">
                <time :datetime="item.entry.at"
                    ><span class="font-semibold text-sidebar-foreground">{{ formatDate(item.entry.at) }}</span> {{ formatTime(item.entry.at) }}</time
                >
            </p>
            <div class="min-w-0">
                <p class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">
                    {{ activitySourceLabels[item.entry.source]
                    }}<template v-if="item.entry.actor"
                        ><SeparatorDot /><span class="font-bold tracking-normal normal-case">{{ item.entry.actor }}</span></template
                    >
                </p>
                <p class="text-sm font-semibold break-words">{{ item.title }}</p>
                <p v-if="item.detail" class="text-[13px] break-words text-muted-foreground">{{ item.detail }}</p>
                <Link
                    v-if="item.entry.source === 'webhook'"
                    :href="routes.gatewayLog(invoiceOf(item.entry))"
                    class="mt-0.5 inline-flex items-center gap-1 text-[13px] font-bold text-primary-text underline decoration-tint-300 decoration-2 underline-offset-4 hover:decoration-tint-600"
                    >Diagnostik di Log Gateway<ArrowRightIcon class="size-3.5 stroke-2" aria-hidden="true"
                /></Link>
            </div>
        </li>
    </ol>
</template>
