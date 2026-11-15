<script setup lang="ts">
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import type { CompositionEntry } from '@/types/content';
import { ChevronDownIcon } from '@heroicons/vue/24/outline';

/**
 * The week's posts per pillar against each pillar's target share, as a summary that opens on demand. Words and numbers
 * carry the meaning, not colour.
 */
defineProps<{ entries: CompositionEntry[] }>();

const share = (count: number, total: number) => (total === 0 ? 0 : Math.round((count / total) * 100));
</script>

<template>
    <details v-for="entry in entries" :key="entry.brand.code" class="group rounded-xl bg-tint-50" data-testid="composition">
        <summary
            class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-xl px-4 py-3 text-sm font-extrabold [&::-webkit-details-marker]:hidden"
        >
            <span>
                Komposisi pilar minggu ini<span v-if="entries.length > 1"><SeparatorDot />{{ entry.brand.name }}</span>
                <span class="font-normal text-muted-foreground">
                    ({{ entry.total }} konten<template v-if="entry.unassigned > 0">, {{ entry.unassigned }} tanpa pilar</template>)</span
                >
            </span>
            <ChevronDownIcon class="size-4 shrink-0 stroke-2 transition-transform group-open:rotate-180 motion-reduce:transition-none" aria-hidden="true" />
        </summary>
        <div class="border-t border-tint-200 px-4 pt-3 pb-4">
            <p v-if="entry.pillars.length === 0" class="text-sm text-muted-foreground">Belum ada pilar untuk brand ini.</p>
            <ul v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <li v-for="pillar in entry.pillars" :key="pillar.id" class="space-y-1" :data-testid="`composition-${pillar.name}`">
                    <p class="flex justify-between gap-2 text-sm">
                        <span class="font-medium">{{ pillar.name }}</span>
                        <!-- A label added by name has no target share; then only the count and share show. -->
                        <span class="tabular"
                            >{{ pillar.count }}<SeparatorDot />{{ share(pillar.count, entry.total) }}%<template v-if="pillar.targetShare > 0">
                                / target {{ pillar.targetShare }}%</template
                            ></span
                        >
                    </p>
                    <div class="relative h-2 rounded-full bg-tint-200" aria-hidden="true">
                        <div
                            class="absolute inset-y-0 left-0 rounded-full bg-primary-strong"
                            :style="{ width: `${Math.min(100, share(pillar.count, entry.total))}%` }"
                        />
                        <div v-if="pillar.targetShare > 0" class="absolute inset-y-[-3px] w-0.5 bg-foreground" :style="{ left: `${pillar.targetShare}%` }" />
                    </div>
                </li>
            </ul>
        </div>
    </details>
</template>
