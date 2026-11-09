<script setup lang="ts">
import { contentQuickStatuses } from '@/lib/content';
import type { ContentStatus } from '@/types/content';

/** The Kalender's main filter: Semua, Draft, Siap posting, Sudah diposting. Pressed state is announced, not only coloured. */
defineProps<{ status: ContentStatus | null }>();
defineEmits<{ change: [status: ContentStatus | null] }>();
</script>

<template>
    <div role="group" aria-label="Status konten" class="inline-flex max-w-full flex-wrap gap-1 rounded-lg border bg-card p-1">
        <button
            v-for="option in contentQuickStatuses"
            :key="option.label"
            type="button"
            class="h-9 rounded-md px-3 text-sm whitespace-nowrap transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none"
            :class="
                status === option.value
                    ? 'bg-primary-strong font-semibold text-primary-foreground'
                    : 'font-medium text-muted-foreground hover:bg-accent hover:text-foreground'
            "
            :aria-pressed="status === option.value"
            @click="$emit('change', option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
