<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { toLocalInput } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { ContentItem, ContentRevision, PostingStep } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

/** A person's statement that the post is live. Posting time is required; the Instagram URL is optional (ADR-0011). */
const props = defineProps<{ item: ContentItem; revision: ContentRevision; checklist: PostingStep[] }>();
const open = defineModel<boolean>('open', { default: false });
const urlHint = 'Opsional. Salin dari tombol Bagikan → Salin tautan di Instagram.';
const publishForm = useForm({ revision: props.revision.id, published_at: '', url: '', note: '' });
const checklistError = computed(() => (publishForm.errors as Record<string, string | undefined>).checklist);

watch(open, (value) => {
    if (value) {
        publishForm.clearErrors();
        publishForm.revision = props.revision.id;
        publishForm.published_at = toLocalInput(new Date());
    }
});

function submit(): void {
    publishForm
        .transform((data) => ({ ...data, checklist: props.checklist }))
        .post(routes.contentPublication(props.item.id), { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Tandai sudah dipublikasikan?"
        :description="`Catat bahwa revisi ${revision.number} sudah diposting di Instagram. Platform tidak memposting apa pun.`"
        confirm-label="Tandai dipublikasikan"
        :submitting="publishForm.processing"
        @confirm="submit"
    >
        <div class="space-y-4">
            <p v-if="checklistError" role="alert" class="text-sm font-medium text-danger">
                {{ checklistError }}
            </p>
            <FormField
                id="publish-at"
                v-slot="{ describedBy, invalid }"
                label="Waktu diposting (WIB) *"
                :error="publishForm.errors.published_at"
                hint="Waktu sebenarnya konten tayang, bukan rencana."
            >
                <Input
                    id="publish-at"
                    v-model="publishForm.published_at"
                    type="datetime-local"
                    class="h-11"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField id="publish-url" v-slot="{ describedBy, invalid }" label="URL posting Instagram" :error="publishForm.errors.url" :hint="urlHint">
                <Input
                    id="publish-url"
                    v-model="publishForm.url"
                    type="url"
                    class="h-11"
                    maxlength="500"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField id="publish-note" v-slot="{ describedBy, invalid }" label="Catatan" :error="publishForm.errors.note" hint="Opsional.">
                <Textarea id="publish-note" v-model="publishForm.note" :rows="2" :maxlength="1000" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
        </div>
    </ConfirmDialog>
</template>
