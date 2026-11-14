<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import ContentPlanFields from '@/features/content/ContentPlanFields.vue';
import { toLocalInput } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { ContentItem, PlanOptions } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/**
 * Ubah rencana (ADR-0013): title, planned time, pillar, campaign, and topic. The plan never makes a revision, so it
 * changes nothing in the post and keeps Ready content Ready (UpdateContentDetails).
 */
const props = defineProps<{ item: ContentItem; options: PlanOptions }>();
const open = defineModel<boolean>('open', { default: false });
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
        description="Judul, jadwal, pilar, campaign, dan topik. Isi posting dan revisinya tidak berubah; konten yang siap posting tetap siap posting."
        confirm-label="Simpan rencana"
        :submitting="form.processing"
        @confirm="submit"
    >
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
