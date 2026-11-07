<script setup lang="ts">
import PanelCard from '@/components/layout/PanelCard.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { copy } from '@/lib/copy';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { AdminNote } from '@/types/backoffice';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ orderId: string; notes: AdminNote[] }>();
const form = useForm({ body: '' });

function submit(): void {
    form.post(routes.transactionNotes(props.orderId), { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>

<template>
    <PanelCard title="Catatan admin">
        <form class="mb-4 space-y-3" @submit.prevent="submit">
            <FormField id="note" v-slot="{ describedBy, invalid }" label="Tambah catatan" :error="form.errors.body">
                <textarea
                    id="note"
                    v-model="form.body"
                    rows="3"
                    class="w-full rounded-md border border-input bg-card px-3 py-2 outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <Button type="submit" :disabled="form.processing || !form.body">{{ form.processing ? copy.saving : copy.save }}</Button>
        </form>
        <EmptyState v-if="notes.length === 0" title="Belum ada catatan" />
        <ul v-else class="space-y-3">
            <li v-for="note in notes" :key="note.id" class="rounded-md bg-muted p-3">
                <p class="whitespace-pre-line">{{ note.body }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ note.author }} · {{ formatDateTime(note.at) }}</p>
            </li>
        </ul>
    </PanelCard>
</template>
