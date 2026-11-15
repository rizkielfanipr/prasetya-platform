<script setup lang="ts">
import { contentQuickStatuses } from '@/lib/content';
import type { ContentStatus } from '@/types/content';

/** The Kalender's main filter: Semua, Draft, Siap posting, Sudah diposting. Pressed state is announced, not only coloured. */
defineProps<{ status: ContentStatus | null }>();
defineEmits<{ change: [status: ContentStatus | null] }>();
</script>

<template>
    <!-- The same segmented control as the Dashboard's brand filter (DESIGN.md). -->
    <div role="group" aria-label="Status konten" class="inline-flex h-11 max-w-full overflow-x-auto rounded-md border border-input bg-card">
        <button
            v-for="option in contentQuickStatuses"
            :key="option.label"
            type="button"
            class="shrink-0 px-4 text-sm font-bold whitespace-nowrap transition-colors duration-150 not-first:border-l not-first:border-input motion-reduce:transition-none"
            :class="status === option.value ? 'bg-primary-strong text-primary-foreground' : 'text-sidebar-foreground hover:bg-tint-100'"
            :aria-pressed="status === option.value"
            @click="$emit('change', option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
