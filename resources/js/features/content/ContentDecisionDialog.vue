<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { revisionAreas } from '@/lib/content';
import type { RevisionArea } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/** Approve, request a revision, or reject one revision. The revision id goes along so a stale decision is refused. */
const props = defineProps<{
    url: string;
    revisionId: string;
    title: string;
    description: string;
    confirmLabel: string;
    noteLabel: string;
    noteRequired: boolean;
    destructive?: boolean;
    /** The field the note is sent as: `note` for review decisions, `reason` for retiring a fact. */
    noteField?: 'note' | 'reason';
    /** Ask which parts to change (revision requests). */
    withAreas?: boolean;
    /** Extra fields sent with the note, e.g. the feedback verdict. */
    extra?: Record<string, string>;
}>();
const open = defineModel<boolean>('open', { default: false });
const form = useForm({ revision: props.revisionId, note: '', areas: [] as RevisionArea[] });
const areaError = () => (form.errors as Record<string, string | undefined>)['areas'];

function toggleArea(area: RevisionArea, on: boolean): void {
    form.areas = on ? [...form.areas, area] : form.areas.filter((value) => value !== area);
}

watch(open, (value) => {
    if (value) {
        form.clearErrors();
        form.revision = props.revisionId;
    }
});

const field = props.noteField ?? 'note';
const noteError = () => (form.errors as Record<string, string | undefined>)[field];

function submit(): void {
    form.transform((data) =>
        field === 'note' ? (props.withAreas ? data : { revision: data.revision, note: data.note }) : { ...props.extra, reason: data.note },
    ).post(props.url, {
        preserveScroll: true,
        onSuccess: () => ((open.value = false), form.reset('note')),
    });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        :title="title"
        :description="description"
        :confirm-label="confirmLabel"
        :destructive="destructive"
        :submitting="form.processing"
        @confirm="submit"
    >
        <fieldset v-if="withAreas" class="space-y-2">
            <legend class="text-sm font-semibold">Bagian yang perlu diubah</legend>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                <div v-for="area in revisionAreas" :key="area.value" class="flex items-center gap-2">
                    <Checkbox
                        :id="`revision-area-${area.value}`"
                        :model-value="form.areas.includes(area.value)"
                        @update:model-value="toggleArea(area.value, $event === true)"
                    />
                    <label :for="`revision-area-${area.value}`" class="text-sm">{{ area.label }}</label>
                </div>
            </div>
            <p class="text-xs text-muted-foreground">Opsional. Bagian yang dicentang dipilihkan untuk AI; yang lain tetap.</p>
            <p v-if="areaError()" role="alert" class="text-xs font-medium text-danger">{{ areaError() }}</p>
        </fieldset>
        <FormField
            id="content-decision-note"
            v-slot="{ describedBy, invalid }"
            :label="noteRequired ? `${noteLabel} *` : noteLabel"
            :error="noteError()"
            :hint="noteRequired ? 'Wajib diisi.' : 'Opsional.'"
        >
            <Textarea id="content-decision-note" v-model="form.note" rows="4" maxlength="2000" :aria-describedby="describedBy" :aria-invalid="invalid" />
        </FormField>
    </ConfirmDialog>
</template>
