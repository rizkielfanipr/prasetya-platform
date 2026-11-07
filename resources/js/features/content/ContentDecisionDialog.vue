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
}>();
const open = defineModel<boolean>('open', { default: false });
const form = useForm({ revision: props.revisionId, note: '' });

watch(open, (value) => {
    if (value) {
        form.clearErrors();
        form.revision = props.revisionId;
    }
});

function submit(): void {
    form.post(props.url, { preserveScroll: true, onSuccess: () => ((open.value = false), form.reset('note')), onError: () => undefined });
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
            :error="form.errors.note"
            :hint="noteRequired ? 'Wajib diisi.' : 'Opsional.'"
        >
            <Textarea id="content-decision-note" v-model="form.note" rows="4" maxlength="2000" :aria-describedby="describedBy" :aria-invalid="invalid" />
        </FormField>
    </ConfirmDialog>
</template>
