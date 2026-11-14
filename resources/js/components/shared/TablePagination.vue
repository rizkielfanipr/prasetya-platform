<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { copy } from '@/lib/copy';
import type { PaginationMeta } from '@/types/backoffice';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';

defineProps<{ meta: PaginationMeta; disabled?: boolean }>();
defineEmits<{ change: [page: number] }>();
</script>

<template>
    <nav v-if="meta.total > 0" aria-label="Halaman tabel" class="flex flex-wrap items-center justify-between gap-3 px-1 py-4">
        <p class="text-[13px] text-muted-foreground tabular" aria-live="polite">{{ copy.showing(meta.from ?? 0, meta.to ?? 0, meta.total) }}</p>
        <div class="flex min-w-0 items-center gap-2">
            <Button
                variant="outline"
                class="h-11 min-w-11 border-input"
                :disabled="disabled || meta.currentPage <= 1"
                @click="$emit('change', meta.currentPage - 1)"
            >
                <ChevronLeftIcon class="stroke-2" aria-hidden="true" />
                <span class="max-sm:sr-only">{{ copy.previous }}</span>
            </Button>
            <span class="px-1 text-[13px] font-bold whitespace-nowrap tabular">{{ copy.pageOf(meta.currentPage, meta.lastPage) }}</span>
            <Button
                variant="outline"
                class="h-11 min-w-11 border-input"
                :disabled="disabled || meta.currentPage >= meta.lastPage"
                @click="$emit('change', meta.currentPage + 1)"
            >
                <span class="max-sm:sr-only">{{ copy.next }}</span>
                <ChevronRightIcon class="stroke-2" aria-hidden="true" />
            </Button>
        </div>
    </nav>
</template>
