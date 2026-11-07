<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { routes } from '@/lib/routes';
import type { ContentBrand } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref, watch } from 'vue';

const props = withDefaults(defineProps<{ brands: ContentBrand[]; defaultDay?: string | null; label?: string }>(), { defaultDay: null, label: 'Buat konten' });
const open = ref(false);
const form = useForm({ brand: props.brands[0]?.code ?? '', title: '', planned_at: '' });

watch(open, (value) => {
    if (value) {
        form.clearErrors();
        form.planned_at = props.defaultDay ? `${props.defaultDay}T10:00` : '';
    }
});

function submit(): void {
    form.post(routes.contentItems, { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Buat konten"
        description="Konten baru dimulai sebagai draft. Isi caption, gambar, dan detail lain di halaman konten."
        confirm-label="Buat draft"
        :submitting="form.processing"
        @confirm="submit"
    >
        <template #trigger>
            <Button class="gap-2"><Plus class="size-4" aria-hidden="true" />{{ label }}</Button>
        </template>
        <div class="space-y-4">
            <div v-if="brands.length > 1" class="space-y-2">
                <label id="new-content-brand-label" class="text-sm font-semibold">Brand</label>
                <Select v-model="form.brand">
                    <SelectTrigger id="new-content-brand" aria-labelledby="new-content-brand-label new-content-brand" class="h-11 w-full"
                        ><SelectValue
                    /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="brand in brands" :key="brand.code" :value="brand.code">{{ brand.name }}</SelectItem>
                    </SelectContent>
                </Select>
                <p v-if="form.errors.brand" role="alert" class="text-xs font-medium text-danger">{{ form.errors.brand }}</p>
            </div>
            <p v-else-if="brands[0]" class="text-sm">
                Brand: <span class="font-semibold">{{ brands[0].name }}</span>
            </p>
            <FormField
                id="new-content-title"
                v-slot="{ describedBy, invalid }"
                label="Judul kerja"
                :error="form.errors.title"
                hint="Untuk tim saja; tidak ikut diposting."
            >
                <Input
                    id="new-content-title"
                    v-model="form.title"
                    class="h-11"
                    maxlength="160"
                    autocomplete="off"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField id="new-content-planned" v-slot="{ describedBy, invalid }" label="Rencana posting (WIB)" :error="form.errors.planned_at">
                <Input
                    id="new-content-planned"
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
