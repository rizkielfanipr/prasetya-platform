<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Textarea } from '@/components/ui/textarea';
import { copyFields, generationStatuses } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { ContentItem, ContentRevision, CopyField, ItemAi } from '@/types/content';
import { router } from '@inertiajs/vue3';
import { ChevronDown, Sparkles } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Buat dengan AI (ADR-0012): drafts caption, hashtags, alt text, and visual direction from the brief as a new Draft
 * revision. AI never marks content Ready, Published, or Archived. `prepare` saves unsaved edits first and answers the
 * revision to draft from (null when saving failed). While a run is queued or running the page refreshes every few
 * seconds and the outcome is announced politely. The runs themselves are listed by ContentAiRuns.
 */
const props = withDefaults(defineProps<{ item: ContentItem; revision: ContentRevision; ai: ItemAi; prepare?: () => Promise<string | null> }>(), {
    prepare: undefined,
});

const POLL_MS = 3000;
const chosen = ref<Record<CopyField, boolean>>({ caption: true, hashtags: true, alt_text: true, visual_direction: true });
const instruction = ref('');
const optionsOpen = ref(false);
const submitting = ref(false);
const announcement = ref('');
const selected = computed(() => copyFields.filter((field) => chosen.value[field.value]).map((field) => field.value));
const active = computed(() => props.ai.runs.some((run) => run.status === 'QUEUED' || run.status === 'RUNNING'));
const label = computed(() => (props.revision.caption ? 'Buat ulang dengan AI' : 'Buat dengan AI'));

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
    <div class="space-y-3 rounded-lg border border-dashed bg-muted/30 p-3" data-testid="ai-panel">
        <div class="flex flex-wrap items-center gap-2">
            <Button
                type="button"
                class="gap-2"
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
                size="sm"
                class="gap-1"
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
            AI menulis caption, hashtag, alt text, dan arahan visual dari brief sebagai revisi Draft baru. Perubahan yang belum disimpan disimpan dulu.
        </p>
        <div v-show="optionsOpen && ai.unavailableReason === null" id="ai-options" class="space-y-3">
            <fieldset class="space-y-2">
                <legend class="text-sm font-semibold">Bagian yang ditulis AI</legend>
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    <div v-for="field in copyFields" :key="field.value" class="flex items-center gap-2">
                        <Checkbox
                            :id="`ai-field-${field.value}`"
                            :model-value="chosen[field.value]"
                            @update:model-value="chosen[field.value] = $event === true"
                        />
                        <label :for="`ai-field-${field.value}`" class="text-sm">{{ field.label }}</label>
                    </div>
                </div>
            </fieldset>
            <div class="space-y-1.5">
                <label for="ai-instruction" class="text-sm font-semibold">Instruksi tambahan (opsional)</label>
                <Textarea id="ai-instruction" v-model="instruction" :rows="2" :maxlength="500" aria-describedby="ai-instruction-hint" />
                <p id="ai-instruction-hint" class="text-xs text-muted-foreground">
                    Misalnya "lebih singkat" atau "sebut domain & hosting". Tetap tunduk pada aturan brand.
                </p>
            </div>
        </div>
        <p class="sr-only" aria-live="polite">{{ announcement }}</p>
    </div>
</template>
