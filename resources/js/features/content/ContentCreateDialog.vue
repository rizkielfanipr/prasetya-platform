<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { routes } from '@/lib/routes';
import type { ContentBrandOption } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

/**
 * Tambah konten (ADR-0012, phase 3): an idea, the planned time in WIB, and a campaign only when one runs that day.
 * The server derives the working title from the idea and keeps the idea as the first brief; a draft is always created,
 * with no AI involved.
 */
const props = withDefaults(defineProps<{ brands: ContentBrandOption[]; defaultDay?: string | null; label?: string }>(), {
    defaultDay: null,
    label: 'Tambah konten',
});
const IDEA_MAX = 1000;
const open = ref(false);
const form = useForm({ brand: props.brands[0]?.code ?? '', idea: '', planned_at: '', campaign: '' });

const day = computed(() => form.planned_at.slice(0, 10));
// Campaigns of the chosen brand that are active and run on the planned day; never required.
const campaigns = computed(() =>
    (props.brands.find((brand) => brand.code === form.brand)?.campaigns ?? []).filter(
        (campaign) => campaign.isActive && day.value !== '' && campaign.startsOn <= day.value && day.value <= campaign.endsOn,
    ),
);
const selectClass = 'h-11 w-full rounded-md border border-input bg-card px-3 text-sm aria-invalid:border-destructive';

watch(open, (value) => {
    if (value) {
        form.clearErrors();
        form.idea = '';
        form.campaign = '';
        form.planned_at = props.defaultDay ? `${props.defaultDay}T10:00` : '';
    }
});
watch(campaigns, (list) => {
    if (!list.some((campaign) => campaign.id === form.campaign)) form.campaign = '';
});

function submit(): void {
    form.transform((data) => ({ ...data, campaign: data.campaign || null })).post(routes.contentItems, {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Tambah konten"
        description="Tulis idenya saja. Konten dimulai sebagai draft; caption dan gambar dilengkapi di halaman konten."
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
            <FormField
                id="new-content-idea"
                v-slot="{ describedBy, invalid }"
                label="Ide atau topik *"
                :error="form.errors.idea"
                hint="Baris pertama menjadi judul kerja. Seluruh ide menjadi brief untuk AI."
            >
                <Textarea
                    id="new-content-idea"
                    v-model="form.idea"
                    :rows="3"
                    :maxlength="IDEA_MAX"
                    placeholder="Contoh: 3 tanda website perlu diperbarui, untuk pemilik toko online"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField id="new-content-planned" v-slot="{ describedBy, invalid }" label="Jadwal posting (WIB) *" :error="form.errors.planned_at">
                <Input
                    id="new-content-planned"
                    v-model="form.planned_at"
                    type="datetime-local"
                    class="h-11"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField
                v-if="campaigns.length > 0"
                id="new-content-campaign"
                v-slot="{ describedBy, invalid }"
                label="Campaign"
                :error="form.errors.campaign"
                hint="Opsional. Hanya campaign yang berjalan pada tanggal ini."
            >
                <select id="new-content-campaign" v-model="form.campaign" :class="selectClass" :aria-describedby="describedBy" :aria-invalid="invalid">
                    <option value="">Tanpa campaign</option>
                    <option v-for="campaign in campaigns" :key="campaign.id" :value="campaign.id">{{ campaign.name }}</option>
                </select>
            </FormField>
        </div>
    </ConfirmDialog>
</template>
