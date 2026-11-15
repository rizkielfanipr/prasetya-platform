<script setup lang="ts">
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { copyFields, formatUsd, generationStatuses } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import type { GenerationRun } from '@/types/content';

/** The item's AI runs, newest first: status in words, parts, who, resulting revision, and usage. */
defineProps<{ runs: GenerationRun[] }>();
</script>

<template>
    <EmptyState v-if="runs.length === 0" title="Belum ada draft AI" />
    <ol v-else class="space-y-2" aria-label="Riwayat draft AI">
        <li v-for="run in runs" :key="run.id" class="flex flex-wrap items-start gap-x-3 gap-y-1 text-sm" :data-testid="`ai-run-${run.status}`">
            <StatusBadge :status="generationStatuses[run.status]" />
            <span class="min-w-0 flex-1">
                {{ run.fields.map((f) => copyFields.find((c) => c.value === f)?.label ?? f).join(', ') }}
                <span class="text-xs text-muted-foreground">
                    <SeparatorDot />{{ run.requestedBy }}<SeparatorDot />{{ formatDateTime(run.createdAt)
                    }}<template v-if="run.resultRevisionNumber"><SeparatorDot />revisi {{ run.resultRevisionNumber }}</template
                    ><template v-if="run.inputTokens + run.outputTokens > 0">
                        <SeparatorDot />{{ run.inputTokens + run.outputTokens }} token<template v-if="run.costUsd > 0"
                            ><SeparatorDot />{{ formatUsd(run.costUsd) }}</template
                        ></template
                    >
                </span>
                <span v-if="run.usedReviewNote || run.instruction" class="block text-xs text-muted-foreground"
                    >{{ run.usedReviewNote ? 'Mengikuti catatan peninjau (alur lama).' : ''
                    }}<template v-if="run.instruction"> Instruksi: “{{ run.instruction }}”</template></span
                >
                <span v-if="run.errorMessage" class="block text-xs break-words text-muted-foreground">{{ run.errorMessage }}</span>
            </span>
        </li>
    </ol>
</template>
