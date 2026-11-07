<script setup lang="ts">
import EmptyState from '@/components/shared/EmptyState.vue';
import { activitySourceLabels, describeActivity } from '@/lib/activity';
import { formatDateTime } from '@/lib/format';
import { toneClasses } from '@/lib/status';
import type { TransactionActivityEntry } from '@/types/backoffice';
import { computed } from 'vue';

const props = defineProps<{ entries: TransactionActivityEntry[] }>();

// Already newest first from the server (ADR-0010); described here so labels keep one home in lib/.
const items = computed(() => props.entries.map((entry) => ({ entry, ...describeActivity(entry) })));
</script>

<template>
    <EmptyState v-if="items.length === 0" title="Belum ada aktivitas" description="Aktivitas pesanan, pembayaran, dan audit akan muncul di sini." />
    <ol v-else class="space-y-4" aria-label="Aktivitas transaksi">
        <li v-for="item in items" :key="item.entry.id" class="relative border-l-2 border-border pl-4" :data-source="item.entry.source">
            <span class="absolute top-1.5 -left-[5px] size-2 rounded-full bg-muted-foreground" aria-hidden="true" />
            <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <!-- The source is written out, so the entry never depends on colour alone. -->
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="toneClasses[item.tone]">{{
                    activitySourceLabels[item.entry.source]
                }}</span>
                <span class="font-semibold break-words">{{ item.title }}</span>
            </p>
            <p class="mt-0.5 text-xs text-muted-foreground">
                <time :datetime="item.entry.at">{{ formatDateTime(item.entry.at) }}</time
                ><template v-if="item.entry.actor"> · {{ item.entry.actor }}</template>
            </p>
            <p v-if="item.detail" class="mt-0.5 text-xs break-words text-muted-foreground">{{ item.detail }}</p>
        </li>
    </ol>
</template>
