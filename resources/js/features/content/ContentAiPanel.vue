<script setup lang="ts">
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import GenerationFeedbackButtons from './GenerationFeedbackButtons.vue';
import { copyFields, formatUsd, generationStatuses } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ContentItem, ContentRevision, CopyField, ItemAi } from '@/types/content';
import { router } from '@inertiajs/vue3';
import { Sparkles } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Ask AI for a draft of some fields. The result is a new draft revision, checked against the brand rules like any
 * other; AI never submits or approves. While a run is queued or running the page refreshes its content every few
 * seconds, and the outcome is announced politely.
 */
const props = defineProps<{ item: ContentItem; revision: ContentRevision; ai: ItemAi }>();

const POLL_MS = 3000;
// Answering a revision request: start from the copy parts the reviewer named; otherwise everything.
const requested = props.ai.reviewRequest?.areas.filter((area): area is CopyField => copyFields.some((field) => field.value === area)) ?? [];
const chosen = ref<Record<CopyField, boolean>>({
    caption: requested.length === 0 || requested.includes('caption'),
    hashtags: requested.length === 0 || requested.includes('hashtags'),
    alt_text: requested.length === 0 || requested.includes('alt_text'),
    visual_direction: requested.length === 0 || requested.includes('visual_direction'),
});
const instruction = ref('');
const submitting = ref(false);
const announcement = ref('');
const selected = computed(() => copyFields.filter((field) => chosen.value[field.value]).map((field) => field.value));
const active = computed(() => props.ai.runs.some((run) => run.status === 'QUEUED' || run.status === 'RUNNING'));

let timer: number | null = null;
function stopPolling(): void {
    if (timer !== null) window.clearInterval(timer);
    timer = null;
}
watch(
    active,
    (running, wasRunning) => {
        stopPolling();
        if (running) {
            timer = window.setInterval(() => {
                router.reload({ only: ['ai', 'item', 'revision', 'revisions', 'findings', 'actions', 'activity'] });
            }, POLL_MS);
        } else if (wasRunning) {
            const last = props.ai.runs[0];
            announcement.value = last ? `${generationStatuses[last.status].label}${last.errorMessage ? `: ${last.errorMessage}` : ''}` : '';
        }
    },
    { immediate: true },
);
onBeforeUnmount(stopPolling);

function generate(): void {
    router.post(
        routes.contentGenerate(props.item.id),
        { revision: props.revision.id, fields: selected.value, instruction: instruction.value || null },
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onSuccess: () => (instruction.value = ''),
            onFinish: () => (submitting.value = false),
        },
    );
}
</script>

<template>
    <div class="space-y-4" data-testid="ai-panel">
        <fieldset class="space-y-2" :disabled="ai.unavailableReason !== null">
            <legend class="text-sm font-semibold">Bagian yang ditulis AI</legend>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                <div v-for="field in copyFields" :key="field.value" class="flex items-center gap-2">
                    <Checkbox
                        :id="`ai-field-${field.value}`"
                        :model-value="chosen[field.value]"
                        :disabled="ai.unavailableReason !== null"
                        @update:model-value="chosen[field.value] = $event === true"
                    />
                    <label :for="`ai-field-${field.value}`" class="text-sm">{{ field.label }}</label>
                </div>
            </div>
        </fieldset>
        <p v-if="ai.reviewRequest" class="text-sm text-muted-foreground" data-testid="ai-follows-review">
            AI akan mengikuti catatan peninjau di atas dan hanya menulis ulang bagian yang dicentang.
        </p>
        <div v-if="ai.unavailableReason === null" class="space-y-1.5">
            <label for="ai-instruction" class="text-sm font-semibold">Instruksi tambahan (opsional)</label>
            <Textarea id="ai-instruction" v-model="instruction" :rows="2" :maxlength="500" aria-describedby="ai-instruction-hint" />
            <p id="ai-instruction-hint" class="text-xs text-muted-foreground">
                Misalnya "lebih singkat" atau "sebut domain & hosting". Tetap tunduk pada aturan brand.
            </p>
        </div>
        <div class="flex flex-col items-start gap-1">
            <Button
                class="gap-2"
                :disabled="ai.unavailableReason !== null || selected.length === 0 || submitting"
                :aria-busy="submitting"
                :aria-describedby="ai.unavailableReason ? 'ai-unavailable' : 'ai-hint'"
                @click="generate"
            >
                <Sparkles class="size-4" aria-hidden="true" />Buat draft dengan AI
            </Button>
            <p v-if="ai.unavailableReason" id="ai-unavailable" class="text-xs text-muted-foreground">{{ ai.unavailableReason }}</p>
            <p v-else id="ai-hint" class="text-xs text-muted-foreground">
                Hasilnya menjadi revisi baru; revisi sekarang tetap tersimpan. Brief, tujuan, dan audiens tidak diubah AI.
            </p>
        </div>
        <p class="sr-only" aria-live="polite">{{ announcement }}</p>

        <ol v-if="ai.runs.length" class="space-y-2 border-t pt-3" aria-label="Riwayat draft AI">
            <li v-for="run in ai.runs" :key="run.id" class="flex flex-wrap items-start gap-x-3 gap-y-1 text-sm" :data-testid="`ai-run-${run.status}`">
                <StatusBadge :status="generationStatuses[run.status]" />
                <span class="min-w-0 flex-1">
                    {{ run.fields.map((f) => copyFields.find((c) => c.value === f)?.label ?? f).join(', ') }}
                    <span class="text-xs text-muted-foreground">
                        · {{ run.requestedBy }} · {{ formatDateTime(run.createdAt)
                        }}<template v-if="run.resultRevisionNumber"> · revisi {{ run.resultRevisionNumber }}</template
                        ><template v-if="run.inputTokens + run.outputTokens > 0">
                            · {{ run.inputTokens + run.outputTokens }} token · {{ formatUsd(run.costUsd) }}</template
                        >
                    </span>
                    <span v-if="run.usedReviewNote || run.instruction" class="block text-xs text-muted-foreground"
                        >{{ run.usedReviewNote ? 'Mengikuti catatan peninjau.' : ''
                        }}<template v-if="run.instruction"> Instruksi: “{{ run.instruction }}”</template></span
                    >
                    <span v-if="run.errorMessage" class="block text-xs break-words text-muted-foreground">{{ run.errorMessage }}</span>
                    <GenerationFeedbackButtons v-if="run.status === 'SUCCEEDED'" :run="run" class="mt-1" />
                </span>
            </li>
        </ol>
    </div>
</template>
