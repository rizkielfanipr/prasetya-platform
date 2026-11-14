<script setup lang="ts">
import ContentInsightPanel from '@/features/content/ContentInsightPanel.vue';
import { postingSteps } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import type { ContentItem, ContentPublication, ContentRevision, InsightSnapshot } from '@/types/content';
import { ExternalLink } from '@lucide/vue';
import ContentSnapshot from './ContentSnapshot.vue';

/**
 * The Published workspace (ADR-0013): the evidence of the post and its performance. The snapshot is the revision that
 * was posted (`postedRevision`, from the publication), never whatever revision is active. Nothing here edits content;
 * Catat insight is the one primary action, and insight stays manual.
 */
defineProps<{ item: ContentItem; publication: ContentPublication; postedRevision: ContentRevision; insights: InsightSnapshot[] }>();
</script>

<template>
    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,22rem)]" data-testid="published-workspace">
        <div class="min-w-0 space-y-5">
            <section aria-labelledby="publication-title" class="space-y-4 rounded-xl border bg-card p-4 sm:p-5" data-testid="publication">
                <h2 id="publication-title" class="text-lg font-bold">Bukti posting</h2>
                <dl class="grid gap-x-6 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold text-muted-foreground">Diposting</dt>
                        <dd class="mt-1 font-semibold">{{ formatDateTime(publication.publishedAt) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-muted-foreground">Dicatat oleh</dt>
                        <dd class="mt-1">{{ publication.actor }} · {{ formatDateTime(publication.recordedAt) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-muted-foreground">Posting Instagram</dt>
                        <dd class="mt-1">
                            <a
                                v-if="publication.url"
                                :href="publication.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex min-h-11 items-center gap-1 font-semibold break-all text-primary-text underline"
                            >
                                Buka di Instagram<ExternalLink class="size-3.5 shrink-0" aria-hidden="true" /><span class="sr-only">(tab baru)</span>
                            </a>
                            <span v-else class="text-muted-foreground">URL tidak dicatat.</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-muted-foreground">Revisi yang diposting</dt>
                        <dd class="mt-1">Revisi {{ publication.revisionNumber }}</dd>
                    </div>
                    <div v-if="publication.note" class="sm:col-span-2">
                        <dt class="text-xs font-semibold text-muted-foreground">Catatan</dt>
                        <dd class="mt-1 break-words whitespace-pre-line">{{ publication.note }}</dd>
                    </div>
                    <div v-if="publication.checklist" class="sm:col-span-2">
                        <dt class="text-xs font-semibold text-muted-foreground">Checklist (alur lama)</dt>
                        <dd class="mt-1">Checklist posting dikonfirmasi ({{ publication.checklist.length }}/{{ postingSteps.length }}).</dd>
                    </div>
                </dl>
            </section>

            <section id="insight" aria-labelledby="insight-title" class="space-y-3 rounded-xl border bg-card p-4 sm:p-5">
                <div>
                    <h2 id="insight-title" class="text-lg font-bold">Insight</h2>
                    <p class="text-sm text-muted-foreground">Angka dari Insight Instagram, dicatat manual.</p>
                </div>
                <ContentInsightPanel :item-id="item.id" :insights="insights" />
            </section>
        </div>

        <section aria-labelledby="posted-title" class="space-y-4 rounded-xl border bg-card p-4 sm:p-5">
            <h2 id="posted-title" class="text-lg font-bold">Konten yang diposting</h2>
            <ContentSnapshot :revision="postedRevision" stacked show-alt />
        </section>
    </div>
</template>
