<script setup lang="ts">
import { copy } from '@/lib/copy';
import { XMarkIcon } from '@heroicons/vue/24/outline';

export interface ActiveFilter {
    key: string;
    label: string;
}

defineProps<{ active: ActiveFilter[] }>();
defineEmits<{ remove: [key: string]; clear: [] }>();
</script>

<template>
    <section aria-label="Filter" class="space-y-3 rounded-xl bg-tint-50 p-3 md:p-4">
        <div class="flex flex-wrap items-end gap-3"><slot /></div>
        <div v-if="active.length" class="flex flex-wrap items-center gap-2" data-testid="active-filters">
            <button
                v-for="filter in active"
                :key="filter.key"
                type="button"
                class="inline-flex min-h-8 items-center gap-1.5 rounded-md bg-tint-200 py-1 pr-2 pl-3 text-xs font-bold text-foreground transition-colors hover:bg-tint-300 motion-reduce:transition-none"
                :aria-label="copy.removeFilter(filter.label)"
                @click="$emit('remove', filter.key)"
            >
                {{ filter.label }}
                <XMarkIcon class="size-3.5 stroke-2" aria-hidden="true" />
            </button>
            <button type="button" class="min-h-8 px-1 py-1 text-xs font-bold text-primary-text underline-offset-2 hover:underline" @click="$emit('clear')">
                {{ copy.resetFilters }}
            </button>
        </div>
    </section>
</template>
