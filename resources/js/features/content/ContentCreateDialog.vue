<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { routes } from '@/lib/routes';
import type { ContentBrandOption } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import ContentPlanFields from './ContentPlanFields.vue';

const props = withDefaults(defineProps<{ brands: ContentBrandOption[]; defaultDay?: string | null; label?: string }>(), {
    defaultDay: null,
    label: 'Tambah konten',
});
const open = ref(false);
const form = useForm({ brand: props.brands[0]?.code ?? '', title: '', planned_at: '', pillar: '', campaign: '', topic: '' });
const options = computed(() => props.brands.find((brand) => brand.code === form.brand) ?? { pillars: [], campaigns: [] });

watch(open, (value) => {
    if (value) {
        form.clearErrors();
        form.planned_at = props.defaultDay ? `${props.defaultDay}T10:00` : '';
    }
});
watch(
    () => form.brand,
    () => {
        form.pillar = '';
        form.campaign = '';
    },
);

function submit(): void {
    form.transform((data) => ({ ...data, pillar: data.pillar || null, campaign: data.campaign || null, topic: data.topic || null })).post(routes.contentItems, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Tambah konten"
        description="Konten baru dimulai sebagai draft. Isi caption, gambar, dan detail lain di halaman konten."
        confirm-label="Buat draft"
        :submitting="form.processing"
        @confirm="submit"
    >
        <template #trigger>
            <Button class="gap-2"><Plus class="size-4" aria-hidden="true" />{{ label }}</Button>
        </template>
        <div class="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
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
            <ContentPlanFields
                v-model:title="form.title"
                v-model:planned-at="form.planned_at"
                v-model:pillar="form.pillar"
                v-model:campaign="form.campaign"
                v-model:topic="form.topic"
                id-prefix="new-content"
                :options="options"
                :errors="form.errors"
            />
        </div>
    </ConfirmDialog>
</template>
