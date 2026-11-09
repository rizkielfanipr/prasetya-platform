<script setup lang="ts">
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { contentStatuses, formatUsd, researchStatuses } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ContentItem, ItemResearch } from '@/types/content';
import { Link, router } from '@inertiajs/vue3';
import { Plus, Search } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ManualSourceDialog from './ManualSourceDialog.vue';
import ResearchSourceCard from './ResearchSourceCard.vue';

/**
 * Research for one post (ADR-0011, phase 4): internal context first, then the sources it cites, web searches, and
 * manual sources. While a search runs the panel refreshes itself.
 */
const props = defineProps<{ item: ContentItem; research: ItemResearch }>();

const POLL_MS = 3000;
const query = ref(props.research.suggestedQuery);
const busy = ref(false);
const manualOpen = ref(false);
const editable = computed(() => props.item.status !== 'PUBLISHED' && props.item.status !== 'REJECTED');
const citedIds = computed(() => new Set(props.research.cited.map((source) => source.id)));
const active = computed(() => props.research.runs.some((run) => run.status === 'QUEUED' || run.status === 'RUNNING'));
const internal = computed(() => props.research.internal);

let timer: number | null = null;
const stop = () => {
    if (timer !== null) window.clearInterval(timer);
    timer = null;
};
watch(
    active,
    (running) => {
        stop();
        if (running) timer = window.setInterval(() => router.reload({ only: ['research', 'activity'] }), POLL_MS);
    },
    { immediate: true },
);
onBeforeUnmount(stop);

const options = { preserveScroll: true, onStart: () => (busy.value = true), onFinish: () => (busy.value = false) };
function search(): void {
    router.post(routes.contentResearch(props.item.id), { query: query.value }, options);
}
function cite(sourceId: string): void {
    router.post(routes.contentSource(props.item.id, sourceId), {}, options);
}
function uncite(sourceId: string): void {
    router.delete(routes.contentSource(props.item.id, sourceId), options);
}
</script>

<template>
    <div class="space-y-6" data-testid="research-panel">
        <section aria-labelledby="internal-heading" class="space-y-2">
            <h3 id="internal-heading" class="text-sm font-semibold">Dari data internal</h3>
            <ul class="space-y-1 text-sm">
                <li>
                    {{ internal.facts }} knowledge berlaku untuk tanggal ini<template v-if="internal.campaigns.length">
                        · kampanye berjalan: {{ internal.campaigns.map((c) => c.name).join(', ') }}</template
                    >.
                </li>
                <li v-if="internal.similar.length === 0" class="text-muted-foreground">Tidak ada konten mirip dalam 90 hari sekitar tanggal ini.</li>
                <li v-else>
                    Konten mirip (hindari mengulang):
                    <ul class="mt-1 list-inside list-disc" data-testid="similar-content">
                        <li v-for="other in internal.similar" :key="other.id">
                            <Link :href="routes.contentItem(other.id)" class="hover:underline">{{ other.title }}</Link>
                            <span class="text-xs text-muted-foreground">
                                · {{ contentStatuses[other.status].label }} · {{ formatDateTime(other.plannedAt) }}</span
                            >
                        </li>
                    </ul>
                </li>
            </ul>
        </section>

        <section aria-labelledby="cited-heading" class="space-y-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 id="cited-heading" class="text-sm font-semibold">Sumber yang dipakai ({{ research.cited.length }})</h3>
                <Button v-if="editable" variant="outline" size="sm" class="gap-1.5" @click="manualOpen = true"
                    ><Plus class="size-4" aria-hidden="true" />Tambah sumber manual</Button
                >
            </div>
            <p v-if="research.cited.length === 0" class="text-sm text-muted-foreground">
                Belum ada. Sumber yang dipakai ikut dibaca AI sebagai bahan latar, bukan fakta.
            </p>
            <ul v-else class="divide-y rounded-lg border" data-testid="cited-sources">
                <li v-for="source in research.cited" :key="source.id" class="flex flex-wrap items-start gap-3 p-3">
                    <ResearchSourceCard :source="source" />
                    <Button v-if="editable" variant="ghost" size="sm" :disabled="busy" :aria-label="`Lepas sumber ${source.title}`" @click="uncite(source.id)"
                        >Lepas</Button
                    >
                </li>
            </ul>
        </section>

        <section aria-labelledby="web-heading" class="space-y-3">
            <h3 id="web-heading" class="text-sm font-semibold">Riset web</h3>
            <form class="flex flex-wrap items-start gap-2" aria-label="Cari di web" @submit.prevent="search">
                <div class="min-w-60 flex-1">
                    <label for="research-query" class="sr-only">Kata kunci riset</label>
                    <Input
                        id="research-query"
                        v-model="query"
                        class="h-10"
                        maxlength="300"
                        :disabled="research.unavailableReason !== null"
                        :aria-describedby="research.unavailableReason ? 'research-unavailable' : undefined"
                    />
                </div>
                <Button type="submit" class="gap-1.5" :disabled="research.unavailableReason !== null || busy || query.trim().length < 3"
                    ><Search class="size-4" aria-hidden="true" />Cari di web</Button
                >
            </form>
            <p v-if="research.unavailableReason" id="research-unavailable" class="text-xs text-muted-foreground">{{ research.unavailableReason }}</p>

            <article v-for="run in research.runs" :key="run.id" class="space-y-2 rounded-lg border p-3" :data-testid="`research-run-${run.status}`">
                <p class="flex flex-wrap items-center gap-2 text-sm">
                    <StatusBadge :status="researchStatuses[run.status]" />
                    <span class="font-medium break-words">“{{ run.query }}”</span>
                    <span class="text-xs text-muted-foreground"
                        >{{ run.requestedBy }} · {{ formatDateTime(run.createdAt)
                        }}<template v-if="run.costUsd > 0"> · {{ formatUsd(run.costUsd) }}</template></span
                    >
                </p>
                <p v-if="run.errorMessage" class="text-sm text-danger">{{ run.errorMessage }}</p>
                <p v-if="run.droppedCount > 0" class="text-xs text-muted-foreground">
                    {{ run.droppedCount }} hasil dibuang karena alamat atau domainnya tidak aman.
                </p>
                <ul v-if="run.sources.length" class="divide-y">
                    <li v-for="source in run.sources" :key="source.id" class="flex flex-wrap items-start gap-3 py-2">
                        <ResearchSourceCard :source="source" />
                        <Button
                            v-if="editable"
                            variant="outline"
                            size="sm"
                            :disabled="busy || citedIds.has(source.id)"
                            :aria-label="citedIds.has(source.id) ? `${source.title} sudah dipakai` : `Pakai sumber ${source.title}`"
                            @click="cite(source.id)"
                            >{{ citedIds.has(source.id) ? 'Dipakai' : 'Pakai' }}</Button
                        >
                    </li>
                </ul>
                <p v-else-if="run.status === 'SUCCEEDED'" class="text-sm text-muted-foreground">Tidak ada hasil yang aman untuk disimpan.</p>
            </article>
        </section>

        <ManualSourceDialog v-model:open="manualOpen" :item-id="item.id" />
    </div>
</template>
