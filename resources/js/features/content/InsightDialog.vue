<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Input } from '@/components/ui/input';
import { insightMetrics, toLocalInput } from '@/lib/content';
import { routes } from '@/lib/routes';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

/** Metrics copied by hand from Instagram's insights. A reading is never edited: a later one is added. */
const props = defineProps<{ itemId: string }>();
const open = defineModel<boolean>('open', { default: false });
type MetricName = (typeof insightMetrics)[number]['name'];
const insightForm = useForm({
    measured_at: '',
    note: '',
    ...(Object.fromEntries(insightMetrics.map((m) => [m.name, ''])) as Record<MetricName, string>),
});
const whenHint = 'Waktu angka ini dibaca di Instagram (WIB).';

watch(open, (value) => {
    if (value) {
        insightForm.reset();
        insightForm.clearErrors();
        insightForm.measured_at = toLocalInput(new Date());
    }
});

function submit(): void {
    insightForm
        .transform((data) => ({
            ...data,
            ...Object.fromEntries(insightMetrics.map((m) => [m.name, data[m.name] === '' ? null : Number(data[m.name])])),
        }))
        .post(routes.contentItemInsights(props.itemId), { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Catat insight"
        description="Salin angka dari Insight postingan di Instagram. Jangkauan wajib; yang lain opsional."
        confirm-label="Simpan insight"
        :submitting="insightForm.processing"
        @confirm="submit"
    >
        <div class="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
            <FormField
                id="insight-measured"
                v-slot="{ describedBy, invalid }"
                label="Waktu dibaca (WIB) *"
                :error="insightForm.errors.measured_at"
                :hint="whenHint"
            >
                <Input
                    id="insight-measured"
                    v-model="insightForm.measured_at"
                    type="datetime-local"
                    class="h-11"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <div class="grid grid-cols-2 gap-3">
                <FormField
                    v-for="metric in insightMetrics"
                    :id="`insight-${metric.name}`"
                    :key="metric.name"
                    v-slot="{ describedBy, invalid }"
                    :label="metric.required ? `${metric.label} *` : metric.label"
                    :error="insightForm.errors[metric.name]"
                >
                    <Input
                        :id="`insight-${metric.name}`"
                        v-model="insightForm[metric.name]"
                        type="number"
                        min="0"
                        inputmode="numeric"
                        class="h-11"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>
            </div>
            <FormField id="insight-note" v-slot="{ describedBy, invalid }" label="Catatan" :error="insightForm.errors.note">
                <Input id="insight-note" v-model="insightForm.note" class="h-11" maxlength="500" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
        </div>
    </ConfirmDialog>
</template>
