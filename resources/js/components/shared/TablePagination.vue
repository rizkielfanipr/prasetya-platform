<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { copy } from '@/lib/copy';
import type { PaginationMeta } from '@/types/backoffice';
import { ChevronLeft, ChevronRight } from '@lucide/vue';

defineProps<{ meta: PaginationMeta; disabled?: boolean }>();
defineEmits<{ change: [page: number] }>();
</script>

<template>
    <nav v-if="meta.total > 0" aria-label="Halaman tabel" class="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3">
        <p class="text-xs text-muted-foreground" aria-live="polite">{{ copy.showing(meta.from ?? 0, meta.to ?? 0, meta.total) }}</p>
        <div class="flex items-center gap-2">
            <Button variant="outline" size="sm" :disabled="disabled || meta.currentPage <= 1" @click="$emit('change', meta.currentPage - 1)">
                <ChevronLeft aria-hidden="true" />
                {{ copy.previous }}
            </Button>
            <span class="px-1 text-xs font-medium whitespace-nowrap">{{ copy.pageOf(meta.currentPage, meta.lastPage) }}</span>
            <Button variant="outline" size="sm" :disabled="disabled || meta.currentPage >= meta.lastPage" @click="$emit('change', meta.currentPage + 1)">
                {{ copy.next }}
                <ChevronRight aria-hidden="true" />
            </Button>
        </div>
    </nav>
</template>
