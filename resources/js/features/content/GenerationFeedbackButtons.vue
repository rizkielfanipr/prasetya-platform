<script setup lang="ts">
import { Button } from '@/components/ui/button';
import ContentDecisionDialog from '@/features/content/ContentDecisionDialog.vue';
import { routes } from '@/lib/routes';
import type { GenerationRun } from '@/types/content';
import { router } from '@inertiajs/vue3';
import { ThumbsDown, ThumbsUp } from '@lucide/vue';
import { ref } from 'vue';

/** A person's verdict on one AI draft, for evaluation only. "Kurang berguna" asks why. */
const props = defineProps<{ run: GenerationRun }>();
const asking = ref(false);
const sending = ref(false);

function useful(): void {
    router.post(
        routes.contentGenerationFeedback(props.run.id),
        { feedback: 'USEFUL' },
        { preserveScroll: true, onStart: () => (sending.value = true), onFinish: () => (sending.value = false) },
    );
}
</script>

<template>
    <span class="inline-flex flex-wrap items-center gap-2">
        <span v-if="run.feedback" class="text-xs font-semibold" :class="run.feedback === 'USEFUL' ? 'text-success' : 'text-danger'">
            {{ run.feedback === 'USEFUL' ? 'Dinilai berguna' : 'Dinilai kurang berguna'
            }}<template v-if="run.feedbackReason">: {{ run.feedbackReason }}</template>
        </span>
        <Button
            variant="outline"
            size="xs"
            class="gap-1"
            :disabled="sending"
            :aria-pressed="run.feedback === 'USEFUL'"
            :aria-label="`Draft AI ${run.id} berguna`"
            @click="useful"
            ><ThumbsUp class="size-3" aria-hidden="true" />Berguna</Button
        >
        <Button
            variant="outline"
            size="xs"
            class="gap-1"
            :disabled="sending"
            :aria-pressed="run.feedback === 'NOT_USEFUL'"
            :aria-label="`Draft AI ${run.id} kurang berguna`"
            @click="asking = true"
            ><ThumbsDown class="size-3" aria-hidden="true" />Kurang berguna</Button
        >
        <ContentDecisionDialog
            v-if="asking"
            :open="true"
            :url="routes.contentGenerationFeedback(run.id)"
            revision-id=""
            title="Kenapa draft ini kurang berguna?"
            description="Dipakai untuk menilai model dan prompt. Tidak mengubah prompt secara otomatis."
            confirm-label="Simpan penilaian"
            note-label="Alasan"
            note-field="reason"
            :extra="{ feedback: 'NOT_USEFUL' }"
            note-required
            @update:open="(value) => !value && (asking = false)"
        />
    </span>
</template>
