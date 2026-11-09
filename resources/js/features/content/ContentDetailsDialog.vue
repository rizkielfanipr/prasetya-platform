<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { toLocalInput } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { ContentItem, PlanOptions } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { CalendarClock } from '@lucide/vue';
import { ref, watch } from 'vue';
import ContentPlanFields from './ContentPlanFields.vue';

const props = defineProps<{ item: ContentItem; options: PlanOptions; blockedReason: string | null }>();
const open = ref(false);
const initial = () => ({
    title: props.item.title,
    planned_at: toLocalInput(props.item.plannedAt),
    pillar: props.item.pillar?.id ?? '',
    campaign: props.item.campaign?.id ?? '',
    topic: props.item.topic ?? '',
});
const form = useForm(initial());

watch(open, (value) => {
    if (value) {
        form.clearErrors();
        Object.assign(form, initial());
    }
});

function submit(): void {
    form.transform((data) => ({ ...data, pillar: data.pillar || null, campaign: data.campaign || null, topic: data.topic || null })).put(
        routes.contentItemDetails(props.item.id),
        {
            preserveScroll: true,
            onSuccess: () => (open.value = false),
        },
    );
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Ubah rencana"
        description="Hanya rencana tim yang berubah. Isi posting, revisi, dan persetujuan tetap."
        confirm-label="Simpan"
        :submitting="form.processing"
        @confirm="submit"
    >
        <template #trigger>
            <Button variant="outline" class="gap-2" :disabled="blockedReason !== null" :title="blockedReason ?? undefined">
                <CalendarClock class="size-4" aria-hidden="true" />Ubah rencana
            </Button>
        </template>
        <div class="max-h-[60vh] overflow-y-auto pr-1">
            <ContentPlanFields
                v-model:title="form.title"
                v-model:planned-at="form.planned_at"
                v-model:pillar="form.pillar"
                v-model:campaign="form.campaign"
                v-model:topic="form.topic"
                id-prefix="content-details"
                :options="options"
                :errors="form.errors"
            />
        </div>
    </ConfirmDialog>
</template>
