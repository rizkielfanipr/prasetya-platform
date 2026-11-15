<script setup lang="ts">
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
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
    <section aria-labelledby="notes-heading" class="border-t border-border pt-4">
        <h2 id="notes-heading" class="text-sm font-extrabold">Catatan Admin</h2>
        <form class="mt-3 space-y-3" @submit.prevent="submit">
            <FormField id="note" v-slot="{ describedBy, invalid }" label="Tambah catatan" :error="form.errors.body">
                <textarea
                    id="note"
                    v-model="form.body"
                    rows="3"
                    class="w-full rounded-md border border-input bg-card px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <Button type="submit" class="h-11" :disabled="form.processing || !form.body">{{ form.processing ? copy.saving : copy.save }}</Button>
        </form>
        <p v-if="notes.length === 0" class="mt-4 text-[13px] text-muted-foreground">Belum ada catatan.</p>
        <ul v-else class="mt-4 space-y-2">
            <li v-for="note in notes" :key="note.id" class="rounded-md bg-tint-50 px-3 py-2.5">
                <p class="text-sm whitespace-pre-line">{{ note.body }}</p>
                <p class="mt-1 text-xs text-muted-foreground">{{ note.author }}<SeparatorDot />{{ formatDateTime(note.at) }}</p>
            </li>
        </ul>
    </section>
</template>
