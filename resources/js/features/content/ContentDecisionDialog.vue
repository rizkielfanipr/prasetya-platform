<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Textarea } from '@/components/ui/textarea';
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
}>();
const open = defineModel<boolean>('open', { default: false });
const form = useForm({ revision: props.revisionId, note: '' });

watch(open, (value) => {
    if (value) {
        form.clearErrors();
        form.revision = props.revisionId;
    }
});

const field = props.noteField ?? 'note';
const noteError = () => (form.errors as Record<string, string | undefined>)[field];

function submit(): void {
    form.transform((data) => (field === 'note' ? data : { reason: data.note })).post(props.url, {
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
