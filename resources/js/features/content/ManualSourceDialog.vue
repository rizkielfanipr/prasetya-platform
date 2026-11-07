<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { routes } from '@/lib/routes';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/** A source found elsewhere, for example with an AI assistant's web search. Empty title: the server reads it safely. */
const props = defineProps<{ itemId: string }>();
const open = defineModel<boolean>('open', { default: false });
const sourceForm = useForm({ url: '', title: '', summary: '', note: '' });
const urlHint = 'Alamat https halaman sumber.';
const titleHint = 'Kosongkan agar diambil otomatis dari halaman.';

watch(open, (value) => {
    if (value) {
        sourceForm.reset();
        sourceForm.clearErrors();
    }
});

function submit(): void {
    sourceForm.post(routes.contentSources(props.itemId), { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Tambah sumber manual"
        description="Sumber dipakai sebagai bahan, bukan fakta bisnis. Harga dan klaim tetap dari fakta yang disetujui."
        confirm-label="Tambah sumber"
        :submitting="sourceForm.processing"
        @confirm="submit"
    >
        <div class="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
            <FormField id="source-url" v-slot="{ describedBy, invalid }" label="URL *" :error="sourceForm.errors.url" :hint="urlHint">
                <Input
                    id="source-url"
                    v-model="sourceForm.url"
                    type="url"
                    class="h-11"
                    maxlength="2048"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField id="source-title" v-slot="{ describedBy, invalid }" label="Judul" :error="sourceForm.errors.title" :hint="titleHint">
                <Input id="source-title" v-model="sourceForm.title" class="h-11" maxlength="300" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
            <FormField id="source-summary" v-slot="{ describedBy, invalid }" label="Ringkasan" :error="sourceForm.errors.summary">
                <Textarea
                    id="source-summary"
                    v-model="sourceForm.summary"
                    :rows="3"
                    :maxlength="2000"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField id="source-note" v-slot="{ describedBy, invalid }" label="Kenapa relevan" :error="sourceForm.errors.note">
                <Input id="source-note" v-model="sourceForm.note" class="h-11" maxlength="500" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
        </div>
    </ConfirmDialog>
</template>
