<script setup lang="ts">
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import { Button } from '@/components/ui/button';
import { formatRate, insightMetrics } from '@/lib/content';
import { formatDateTime, formatNumber } from '@/lib/format';
import type { InsightSnapshot } from '@/types/content';
import { PlusIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';
import InsightDialog from './InsightDialog.vue';

/** A published post's manual Instagram metrics: the latest reading first, earlier readings kept. */
const props = defineProps<{ itemId: string; insights: InsightSnapshot[] }>();
const open = ref(false);
const latest = computed(() => props.insights[0] ?? null);
</script>

<template>
    <div class="space-y-4" data-testid="insight-panel">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-muted-foreground">
                <template v-if="latest">Dibaca {{ formatDateTime(latest.measuredAt) }} oleh {{ latest.recordedBy }}.</template>
                <template v-else>Belum ada insight. Catat setelah 1–2 hari, saat angkanya mulai stabil.</template>
            </p>
            <Button class="h-11 gap-1.5" @click="open = true"><PlusIcon class="size-4 stroke-2" aria-hidden="true" />Catat insight</Button>
        </div>
        <!-- A plain list of the reading, engagement first; no metric tiles (DESIGN.md). -->
        <dl v-if="latest" class="grid grid-cols-2 gap-x-6 gap-y-3 rounded-xl bg-tint-50 px-4 py-3 sm:grid-cols-3 xl:grid-cols-5">
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Engagement</dt>
                <dd class="mt-0.5 text-sm font-extrabold tabular">{{ formatRate(latest.engagement) }}</dd>
            </div>
            <div v-for="metric in insightMetrics" :key="metric.key">
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">{{ metric.label }}</dt>
                <dd class="mt-0.5 text-sm font-bold tabular">{{ latest[metric.key] === null ? '—' : formatNumber(latest[metric.key] ?? 0) }}</dd>
            </div>
        </dl>
        <p class="text-xs text-muted-foreground">Engagement = (suka + komentar + simpan + bagikan) ÷ jangkauan.</p>
        <details v-if="insights.length > 1" class="text-sm">
            <summary class="min-h-11 cursor-pointer content-center font-bold text-primary-text">Bacaan sebelumnya ({{ insights.length - 1 }})</summary>
            <ul class="mt-2 space-y-1">
                <li v-for="snapshot in insights.slice(1)" :key="snapshot.id">
                    {{ formatDateTime(snapshot.measuredAt) }}<SeparatorDot />jangkauan {{ formatNumber(snapshot.reach) }}<SeparatorDot />engagement
                    {{ formatRate(snapshot.engagement) }}<template v-if="snapshot.note"><SeparatorDot />{{ snapshot.note }}</template>
                </li>
            </ul>
        </details>
        <InsightDialog v-model:open="open" :item-id="itemId" />
    </div>
</template>
