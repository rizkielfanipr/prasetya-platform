<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { routes } from '@/lib/routes';
import type { ContentPillar } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{ brandId: string; pillar: ContentPillar | null }>();
const open = defineModel<boolean>('open', { default: false });
const form = useForm({ name: '', description: '', target_share: '0', is_active: true });

watch(open, (value) => {
    if (!value) return;
    form.clearErrors();
    Object.assign(form, {
        name: props.pillar?.name ?? '',
        description: props.pillar?.description ?? '',
        target_share: String(props.pillar?.targetShare ?? 0),
        is_active: props.pillar?.isActive ?? true,
    });
});

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    const data = form.transform((d) => ({ ...d, target_share: Number(d.target_share) }));
    if (props.pillar) data.put(routes.contentPillar(props.brandId, props.pillar.id), options);
    else data.post(routes.contentPillars(props.brandId), options);
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        :title="pillar ? `Ubah pilar ${pillar.name}` : 'Tambah pilar'"
        description="Target adalah porsi konten seminggu. Total target pilar aktif paling banyak 100%."
        confirm-label="Simpan pilar"
        :submitting="form.processing"
        @confirm="submit"
    >
        <div class="space-y-4">
            <FormField id="pillar-name" v-slot="{ describedBy, invalid }" label="Nama" :error="form.errors.name">
                <Input id="pillar-name" v-model="form.name" class="h-11" maxlength="80" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
            <FormField id="pillar-description" v-slot="{ describedBy, invalid }" label="Deskripsi" :error="form.errors.description">
                <Textarea
                    id="pillar-description"
                    v-model="form.description"
                    :rows="2"
                    :maxlength="1000"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField id="pillar-target" v-slot="{ describedBy, invalid }" label="Target (%)" :error="form.errors.target_share">
                <Input
                    id="pillar-target"
                    v-model="form.target_share"
                    type="number"
                    min="0"
                    max="100"
                    class="h-11"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <div class="flex items-center gap-2">
                <Checkbox id="pillar-active" :model-value="form.is_active" @update:model-value="form.is_active = $event === true" />
                <label for="pillar-active" class="text-sm">Aktif (dapat dipilih untuk konten baru)</label>
            </div>
        </div>
    </ConfirmDialog>
</template>
