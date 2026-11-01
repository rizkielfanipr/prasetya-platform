<script setup lang="ts">
import { copy } from '@/lib/copy';
import { X } from '@lucide/vue';

export interface ActiveFilter {
    key: string;
    label: string;
}

defineProps<{ active: ActiveFilter[] }>();
defineEmits<{ remove: [key: string]; clear: [] }>();
</script>

<template>
    <section aria-label="Filter" class="space-y-3">
        <div class="flex flex-wrap items-end gap-3"><slot /></div>
        <div v-if="active.length" class="flex flex-wrap items-center gap-2" data-testid="active-filters">
            <button
                v-for="filter in active"
                :key="filter.key"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border bg-secondary px-3 py-1.5 text-xs font-semibold"
                :aria-label="copy.removeFilter(filter.label)"
                @click="$emit('remove', filter.key)"
            >
                {{ filter.label }}
                <X class="size-3.5" aria-hidden="true" />
            </button>
            <button type="button" class="px-1 py-1.5 text-xs font-semibold text-primary-text underline-offset-2 hover:underline" @click="$emit('clear')">
                {{ copy.resetFilters }}
            </button>
        </div>
    </section>
</template>
