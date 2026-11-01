<script setup lang="ts">
import { formatDateTime } from '@/lib/format';
import { orderStatuses, sourceLabels } from '@/lib/status';
import type { OrderHistoryEntry } from '@/types/backoffice';

defineProps<{ entries: OrderHistoryEntry[] }>();
</script>

<template>
    <ol class="space-y-4" aria-label="Riwayat status pesanan">
        <li v-for="entry in entries" :key="entry.id" class="relative border-l-2 border-border pl-4">
            <span class="absolute top-1.5 -left-[5px] size-2 rounded-full bg-muted-foreground" aria-hidden="true" />
            <p class="font-semibold">{{ entry.from ? `${orderStatuses[entry.from].label} → ` : '' }}{{ orderStatuses[entry.to].label }}</p>
            <p class="text-xs text-muted-foreground">{{ formatDateTime(entry.at) }} · {{ entry.actor ?? sourceLabels[entry.source] ?? entry.source }}</p>
            <p v-if="entry.reason" class="mt-0.5 text-xs">Alasan: {{ entry.reason }}</p>
        </li>
    </ol>
</template>
