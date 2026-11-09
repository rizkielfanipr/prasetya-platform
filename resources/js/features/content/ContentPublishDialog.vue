<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { toLocalInput } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { ContentItem, ContentRevision } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/**
 * A person's statement that the post is live (ADR-0012): the actual posting time, now in WIB by default, is required;
 * the Instagram URL and note are optional. No checklist. The publication cannot be changed afterwards.
 */
const props = defineProps<{ item: ContentItem; revision: ContentRevision }>();
const open = defineModel<boolean>('open', { default: false });
const urlHint = 'Opsional. Salin dari tombol Bagikan → Salin tautan di Instagram.';
const publishForm = useForm({ revision: props.revision.id, published_at: '', url: '', note: '' });

watch(open, (value) => {
    if (value) {
        publishForm.clearErrors();
        publishForm.revision = props.revision.id;
        publishForm.published_at = toLocalInput(new Date());
    }
});

function submit(): void {
    publishForm.post(routes.contentPublication(props.item.id), { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Tandai sudah diposting?"
        :description="`Catat bahwa revisi ${revision.number} sudah diposting di Instagram. Platform tidak memposting apa pun, dan catatan ini tidak dapat diubah.`"
        confirm-label="Tandai sudah diposting"
        :submitting="publishForm.processing"
        @confirm="submit"
    >
        <div class="space-y-4">
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
