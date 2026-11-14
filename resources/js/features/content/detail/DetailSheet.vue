<script setup lang="ts">
import ErrorState from '@/components/shared/ErrorState.vue';
import LoadingState from '@/components/shared/LoadingState.vue';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import type { EvidenceState } from '@/composables/useItemEvidence';
import { TriangleAlert } from '@lucide/vue';

/**
 * A named secondary surface of the detail page (ADR-0013), full height from the right. Reka gives it the focus trap,
 * Escape to close, and focus back to whatever opened it. With `state` it also shows loading, failure with retry, and
 * a stale-data note when a quiet refresh failed.
 */
withDefaults(defineProps<{ title: string; description: string; state?: EvidenceState; error?: string | null; testid?: string }>(), {
    state: 'ready',
    error: null,
    testid: undefined,
});
const open = defineModel<boolean>('open', { default: false });
defineEmits<{ retry: [] }>();
</script>

<template>
    <Sheet v-model:open="open">
        <!-- design.md motion: drawers move for 150 to 220 ms. -->
        <SheetContent side="right" class="w-full gap-0 p-0 data-[state=closed]:duration-150 data-[state=open]:duration-200 sm:max-w-xl" :data-testid="testid">
            <header class="border-b px-5 pt-5 pr-14 pb-4">
                <SheetTitle class="text-lg font-bold">{{ title }}</SheetTitle>
                <SheetDescription class="mt-1 text-sm text-muted-foreground">{{ description }}</SheetDescription>
            </header>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                <LoadingState v-if="state === 'loading' || state === 'idle'" :rows="5" :label="`Memuat ${title.toLowerCase()}`" class="p-0" />
                <ErrorState v-else-if="state === 'error'" :description="error ?? undefined" retryable @retry="$emit('retry')" />
                <template v-else>
                    <p v-if="error" role="status" class="mb-4 flex items-start gap-2 rounded-lg bg-warning-surface p-3 text-sm text-warning">
                        <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <span>Data terbaru belum dapat dimuat. Yang tampil adalah data sebelumnya. {{ error }}</span>
                    </p>
                    <slot />
                </template>
            </div>
            <footer v-if="$slots.footer" class="border-t px-5 py-4"><slot name="footer" /></footer>
        </SheetContent>
    </Sheet>
</template>
