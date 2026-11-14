<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import { copyFields, generationStatuses } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ContentItem, ContentRevision, CopyField, ItemAi } from '@/types/content';
import { router } from '@inertiajs/vue3';
import { ChevronDown, Sparkles } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Buat dengan AI as assistance, not a console (ADR-0013): one action, one sentence on what it writes, and the latest
 * outcome in words. Opsi AI holds the parts to write and an optional instruction. AI only ever writes a new Draft
 * revision. `prepare` saves unsaved edits first and answers the revision to draft from (null when saving failed).
 * While a run is queued or running the Draft props refresh every few seconds and the outcome is announced politely;
 * provider, model, tokens, and the run history live in Riwayat versi.
 */
const props = withDefaults(
    defineProps<{ item: ContentItem; revision: ContentRevision; ai: ItemAi; prepare?: () => Promise<string | null>; dirty?: boolean }>(),
    {
        prepare: undefined,
        dirty: false,
    },
);

const POLL_MS = 3000;
const chosen = ref<Record<CopyField, boolean>>({ caption: true, hashtags: true, alt_text: true, visual_direction: true });
const instruction = ref('');
const optionsOpen = ref(false);
const submitting = ref(false);
const announcement = ref('');
const selected = computed(() => copyFields.filter((field) => chosen.value[field.value]).map((field) => field.value));
const active = computed(() => props.ai.current?.status === 'QUEUED' || props.ai.current?.status === 'RUNNING');
const label = computed(() => (props.revision.caption ? 'Buat ulang dengan AI' : 'Buat dengan AI'));

const outcome = computed(() => {
    const run = props.ai.current;
    if (!run || active.value) return null;
    switch (run.status) {
        case 'SUCCEEDED':
            return `Draft AI terakhir menjadi revisi ${run.resultRevisionNumber ?? '–'}, ${formatDateTime(run.finishedAt ?? run.createdAt)}.`;
        case 'FAILED':
            return `Percobaan AI terakhir gagal${run.errorMessage ? `: ${run.errorMessage}` : '.'}`;
        case 'DISCARDED':
            return 'Draft AI terakhir tidak dipakai karena konten berubah saat AI menulis.';
        default:
            return null;
    }
});

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
            announcement.value = 'AI sedang menulis draft.';
            timer = window.setInterval(() => {
                router.reload({ only: ['ai', 'item', 'revision', 'findings', 'actions'] });
            }, POLL_MS);
        } else if (wasRunning) {
            const last = props.ai.current;
            announcement.value = last ? `${generationStatuses[last.status].label}${last.errorMessage ? `: ${last.errorMessage}` : ''}` : '';
        }
    },
    { immediate: true },
);
onBeforeUnmount(stopPolling);

async function generate(): Promise<void> {
    submitting.value = true;
    const revision = props.prepare ? await props.prepare() : props.revision.id;
    if (revision === null) {
        submitting.value = false;
        return;
    }
    router.post(
        routes.contentGenerate(props.item.id),
        { revision, fields: selected.value, instruction: instruction.value || null },
        {
            preserveScroll: true,
            onSuccess: () => (instruction.value = ''),
            onFinish: () => (submitting.value = false),
        },
    );
}
</script>

<template>
    <div class="space-y-2" data-testid="ai-panel">
        <div class="flex flex-wrap items-center gap-2">
            <!-- Sparkles marks AI help across Content Studio, so it reads apart from the manual actions. -->
            <Button
                type="button"
                variant="secondary"
                class="h-11 gap-2"
                :disabled="ai.unavailableReason !== null || selected.length === 0 || submitting || active"
                :aria-busy="submitting || active"
                :aria-describedby="ai.unavailableReason ? 'ai-unavailable' : 'ai-hint'"
                @click="generate"
            >
                <Sparkles class="size-4" aria-hidden="true" />{{ active ? 'AI sedang menulis…' : label }}
            </Button>
            <Button
                v-if="ai.unavailableReason === null"
                type="button"
                variant="ghost"
                class="h-11 gap-1"
                aria-controls="ai-options"
                :aria-expanded="optionsOpen"
                @click="optionsOpen = !optionsOpen"
            >
                Opsi AI<ChevronDown
                    class="size-4 transition-transform motion-reduce:transition-none"
                    :class="{ 'rotate-180': optionsOpen }"
                    aria-hidden="true"
                />
            </Button>
        </div>
        <p v-if="ai.unavailableReason" id="ai-unavailable" class="text-xs text-muted-foreground">{{ ai.unavailableReason }}</p>
        <p v-else id="ai-hint" class="text-xs text-muted-foreground">
            AI menulis caption, hashtag, alt text, dan arahan visual dari brief sebagai revisi Draft baru.<template v-if="dirty">
                Perubahan yang belum disimpan disimpan dulu.</template
            >
        </p>
        <p v-if="outcome" class="text-xs" data-testid="ai-outcome">{{ outcome }}</p>
        <div v-show="optionsOpen && ai.unavailableReason === null" id="ai-options" class="space-y-3 rounded-lg bg-muted/50 p-3">
            <fieldset class="space-y-2">
                <legend class="text-sm font-semibold">Bagian yang ditulis AI</legend>
                <div class="flex flex-wrap gap-x-5 gap-y-1">
                    <div v-for="field in copyFields" :key="field.value" class="flex min-h-11 items-center gap-2">
                        <Checkbox
                            :id="`ai-field-${field.value}`"
                            :model-value="chosen[field.value]"
                            @update:model-value="chosen[field.value] = $event === true"
                        />
                        <label :for="`ai-field-${field.value}`" class="cursor-pointer text-sm">{{ field.label }}</label>
                    </div>
                </div>
            </fieldset>
            <div class="space-y-1.5">
                <label for="ai-instruction" class="text-sm font-semibold">Instruksi tambahan (opsional)</label>
                <Textarea id="ai-instruction" v-model="instruction" :rows="2" :maxlength="500" aria-describedby="ai-instruction-hint" />
                <p id="ai-instruction-hint" class="text-xs text-muted-foreground">
                    Misalnya "lebih singkat" atau "sebut domain & hosting". Tetap tunduk pada aturan brand. AI hanya membuat Draft; Anda yang menandai siap
                    posting.
                </p>
            </div>
        </div>
        <p class="sr-only" aria-live="polite">{{ announcement }}</p>
    </div>
</template>
