<script setup lang="ts">
import type { CompositionEntry } from '@/types/content';
import { ChevronDown } from '@lucide/vue';

/**
 * The week's posts per pillar against each pillar's target share, as a summary that opens on demand. Words and numbers
 * carry the meaning, not colour.
 */
defineProps<{ entries: CompositionEntry[] }>();

const share = (count: number, total: number) => (total === 0 ? 0 : Math.round((count / total) * 100));
</script>

<template>
    <details v-for="entry in entries" :key="entry.brand.code" class="group rounded-xl border bg-card" data-testid="composition">
        <summary
            class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl p-4 text-sm font-semibold focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none [&::-webkit-details-marker]:hidden"
        >
            <span>
                Komposisi pilar minggu ini<span v-if="entries.length > 1"> · {{ entry.brand.name }}</span>
                <span class="font-normal text-muted-foreground">
                    ({{ entry.total }} konten<template v-if="entry.unassigned > 0">, {{ entry.unassigned }} tanpa pilar</template>)</span
                >
            </span>
            <ChevronDown class="size-4 shrink-0 transition-transform group-open:rotate-180 motion-reduce:transition-none" aria-hidden="true" />
        </summary>
        <div class="border-t px-4 pt-3 pb-4">
            <p v-if="entry.pillars.length === 0" class="text-sm text-muted-foreground">Belum ada pilar untuk brand ini.</p>
            <ul v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <li v-for="pillar in entry.pillars" :key="pillar.id" class="space-y-1" :data-testid="`composition-${pillar.name}`">
                    <p class="flex justify-between gap-2 text-sm">
                        <span class="font-medium">{{ pillar.name }}</span>
                        <span class="tabular">{{ pillar.count }} · {{ share(pillar.count, entry.total) }}% / target {{ pillar.targetShare }}%</span>
                    </p>
                    <div class="relative h-2 rounded-full bg-muted" aria-hidden="true">
                        <div
                            class="absolute inset-y-0 left-0 rounded-full bg-primary-strong"
                            :style="{ width: `${Math.min(100, share(pillar.count, entry.total))}%` }"
                        />
                        <div class="absolute inset-y-[-3px] w-0.5 bg-foreground" :style="{ left: `${pillar.targetShare}%` }" />
                    </div>
                </li>
            </ul>
        </div>
    </details>
</template>
