<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { formatRate, insightMetrics } from '@/lib/content';
import { formatDateTime, formatNumber } from '@/lib/format';
import type { InsightSnapshot } from '@/types/content';
import { Plus } from '@lucide/vue';
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
            <Button class="h-11 gap-1.5" @click="open = true"><Plus class="size-4" aria-hidden="true" />Catat insight</Button>
        </div>
        <dl v-if="latest" class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
            <div class="rounded-lg border p-3">
                <dt class="text-xs font-semibold text-muted-foreground">Engagement</dt>
                <dd class="mt-1 text-lg font-bold tabular">{{ formatRate(latest.engagement) }}</dd>
            </div>
            <div v-for="metric in insightMetrics" :key="metric.key" class="rounded-lg border p-3">
                <dt class="text-xs font-semibold text-muted-foreground">{{ metric.label }}</dt>
                <dd class="mt-1 text-lg font-bold tabular">{{ latest[metric.key] === null ? '—' : formatNumber(latest[metric.key] ?? 0) }}</dd>
            </div>
        </dl>
        <p class="text-xs text-muted-foreground">Engagement = (suka + komentar + simpan + bagikan) ÷ jangkauan.</p>
        <details v-if="insights.length > 1" class="text-sm">
            <summary class="cursor-pointer font-semibold">Bacaan sebelumnya ({{ insights.length - 1 }})</summary>
            <ul class="mt-2 space-y-1">
                <li v-for="snapshot in insights.slice(1)" :key="snapshot.id">
                    {{ formatDateTime(snapshot.measuredAt) }} · jangkauan {{ formatNumber(snapshot.reach) }} · engagement {{ formatRate(snapshot.engagement)
                    }}<template v-if="snapshot.note"> · {{ snapshot.note }}</template>
                </li>
            </ul>
        </details>
        <InsightDialog v-model:open="open" :item-id="itemId" />
    </div>
</template>
