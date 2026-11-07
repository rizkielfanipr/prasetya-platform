<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toLocalInput } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { ContentItem } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { CalendarClock } from '@lucide/vue';
import { ref, watch } from 'vue';

const props = defineProps<{ item: ContentItem; blockedReason: string | null }>();
const open = ref(false);
const form = useForm({ title: props.item.title, planned_at: toLocalInput(props.item.plannedAt) });

watch(open, (value) => {
    if (value) {
        form.clearErrors();
        form.title = props.item.title;
        form.planned_at = toLocalInput(props.item.plannedAt);
    }
});

function submit(): void {
    form.put(routes.contentItemDetails(props.item.id), { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Ubah judul & jadwal"
        description="Hanya rencana tim yang berubah. Isi posting, revisi, dan persetujuan tetap."
        confirm-label="Simpan"
        :submitting="form.processing"
        @confirm="submit"
    >
        <template #trigger>
            <Button variant="outline" class="gap-2" :disabled="blockedReason !== null" :title="blockedReason ?? undefined">
                <CalendarClock class="size-4" aria-hidden="true" />Ubah judul & jadwal
            </Button>
        </template>
        <div class="space-y-4">
            <FormField id="content-details-title" v-slot="{ describedBy, invalid }" label="Judul kerja" :error="form.errors.title">
                <Input id="content-details-title" v-model="form.title" class="h-11" maxlength="160" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
            <FormField id="content-details-planned" v-slot="{ describedBy, invalid }" label="Rencana posting (WIB)" :error="form.errors.planned_at">
                <Input
                    id="content-details-planned"
                    v-model="form.planned_at"
                    type="datetime-local"
                    class="h-11"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
        </div>
    </ConfirmDialog>
</template>
