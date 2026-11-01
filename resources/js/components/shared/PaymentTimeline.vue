<script setup lang="ts">
import { formatDateTime } from '@/lib/format';
import { eventTypeLabels, paymentStatuses, sourceLabels } from '@/lib/status';
import type { PaymentEventEntry } from '@/types/backoffice';
import EmptyState from './EmptyState.vue';

defineProps<{ events: PaymentEventEntry[] }>();

function title(event: PaymentEventEntry): string {
    const base = eventTypeLabels[event.type] ?? event.type;

    return event.type === 'STATUS_CHANGED' && event.to
        ? `${base}: ${event.from ? paymentStatuses[event.from].label : '—'} → ${paymentStatuses[event.to].label}`
        : base;
}
</script>

<template>
    <EmptyState v-if="events.length === 0" title="Belum ada kejadian pembayaran" />
    <ol v-else class="space-y-4" aria-label="Riwayat pembayaran">
        <li v-for="event in events" :key="event.id" class="relative border-l-2 border-border pl-4">
            <span class="absolute top-1.5 -left-[5px] size-2 rounded-full bg-muted-foreground" aria-hidden="true" />
            <p class="font-semibold">{{ title(event) }}</p>
            <p class="text-xs text-muted-foreground">
                <time :datetime="event.at">{{ formatDateTime(event.at) }}</time> · {{ sourceLabels[event.source] ?? event.source }}
            </p>
            <p v-if="event.detail" class="mt-0.5 text-xs text-muted-foreground">{{ event.detail }}</p>
        </li>
    </ol>
</template>
