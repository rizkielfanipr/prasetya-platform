<script setup lang="ts">
import { formatDateTime } from '@/lib/format';
import { auditActionLabels } from '@/lib/status';
import type { AuditEntry, JsonState } from '@/types/backoffice';
import EmptyState from './EmptyState.vue';

defineProps<{ entries: AuditEntry[] }>();

function describe(state: JsonState | null): string {
    return state
        ? Object.entries(state)
              .map(([key, value]) => `${key}: ${String(value)}`)
              .join(', ')
        : '—';
}
</script>

<template>
    <EmptyState v-if="entries.length === 0" title="Belum ada catatan audit" />
    <ol v-else class="space-y-4" aria-label="Riwayat audit">
        <li v-for="entry in entries" :key="entry.id" class="relative border-l-2 border-border pl-4">
            <span class="absolute top-1.5 -left-[5px] size-2 rounded-full bg-muted-foreground" aria-hidden="true" />
            <p class="font-semibold">{{ auditActionLabels[entry.action] ?? entry.action }}</p>
            <p class="text-xs text-muted-foreground">
                {{ entry.actor }} · <time :datetime="entry.at">{{ formatDateTime(entry.at) }}</time>
            </p>
            <p v-if="entry.previousState || entry.newState" class="mt-0.5 text-xs break-words text-muted-foreground">
                {{ describe(entry.previousState) }} → {{ describe(entry.newState) }}
            </p>
            <p v-if="entry.reason" class="mt-0.5 text-xs">Alasan: {{ entry.reason }}</p>
        </li>
    </ol>
</template>
