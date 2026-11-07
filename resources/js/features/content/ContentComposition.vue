<script setup lang="ts">
import type { CompositionEntry } from '@/types/content';

/** The week's posts per pillar against each pillar's target share. Words and numbers carry the meaning, not colour. */
defineProps<{ entries: CompositionEntry[] }>();

const share = (count: number, total: number) => (total === 0 ? 0 : Math.round((count / total) * 100));
</script>

<template>
    <section v-for="entry in entries" :key="entry.brand.code" class="rounded-xl border bg-card p-4" :aria-labelledby="`composition-${entry.brand.code}`">
        <h2 :id="`composition-${entry.brand.code}`" class="text-sm font-semibold">
            Komposisi pilar minggu ini<span v-if="entries.length > 1"> · {{ entry.brand.name }}</span>
            <span class="font-normal text-muted-foreground"> ({{ entry.total }} konten)</span>
        </h2>
        <p v-if="entry.pillars.length === 0" class="mt-1 text-sm text-muted-foreground">Belum ada pilar. Tambahkan di Brand &amp; Template.</p>
        <ul v-else class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
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
        <p v-if="entry.unassigned > 0" class="mt-2 text-xs text-muted-foreground">{{ entry.unassigned }} konten belum punya pilar.</p>
    </section>
</template>
