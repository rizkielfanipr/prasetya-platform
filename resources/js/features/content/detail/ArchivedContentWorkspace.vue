<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import type { ArchiveEvidence, ContentItem, ContentRevision, DetailSurface } from '@/types/content';
import { Archive } from '@lucide/vue';
import ContentSnapshot from './ContentSnapshot.vue';

/**
 * The Archived workspace (ADR-0013): read-only. Who archived it and why when the audit says so, the last schedule and
 * revision, and the way into the evidence. No editor, AI, posting, or lifecycle control is rendered.
 */
defineProps<{ item: ContentItem; revision: ContentRevision; archive: ArchiveEvidence | null }>();
const emit = defineEmits<{ open: [surface: DetailSurface] }>();
</script>

<template>
    <section aria-labelledby="archived-title" class="space-y-5 rounded-xl border bg-card p-4 sm:p-5" data-testid="archived-workspace">
        <h2 id="archived-title" class="text-lg font-bold">Ringkasan arsip</h2>
        <div class="flex items-start gap-2 rounded-lg bg-neutral-surface p-3 text-sm text-neutral" data-testid="archive-summary">
            <Archive class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <div class="space-y-1">
                <p v-if="archive" class="font-semibold">Diarsipkan oleh {{ archive.actor }}, {{ formatDateTime(archive.at) }}.</p>
                <p v-else class="font-semibold">Konten ini diarsipkan.</p>
                <p class="break-words">{{ archive?.reason ? `Alasan: ${archive.reason}` : 'Tidak ada alasan yang dicatat.' }}</p>
                <p>Konten ini tidak dapat diubah atau diposting lagi. Revisi dan riwayatnya tetap tersimpan.</p>
            </div>
        </div>
        <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Jadwal terakhir</dt>
                <dd class="mt-1">{{ formatDateTime(item.plannedAt) }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Revisi terakhir</dt>
                <dd class="mt-1">Revisi {{ revision.number }}</dd>
            </div>
        </dl>
        <ContentSnapshot :revision="revision" show-alt />
        <div class="flex flex-wrap gap-3 border-t pt-4">
            <Button class="h-11" @click="emit('open', 'history')">Lihat riwayat versi</Button>
            <Button variant="outline" class="h-11" @click="emit('open', 'activity')">Lihat aktivitas</Button>
        </div>
    </section>
</template>
