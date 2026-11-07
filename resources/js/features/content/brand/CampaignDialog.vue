<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { campaignPriorities } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { CampaignPriority, ContentCampaign } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{ brandId: string; campaign: ContentCampaign | null; priorities: CampaignPriority[] }>();
const open = defineModel<boolean>('open', { default: false });
const form = useForm({ name: '', objective: '', starts_on: '', ends_on: '', priority: 'NORMAL' as CampaignPriority, is_active: true });

watch(open, (value) => {
    if (!value) return;
    form.clearErrors();
    Object.assign(form, {
        name: props.campaign?.name ?? '',
        objective: props.campaign?.objective ?? '',
        starts_on: props.campaign?.startsOn ?? '',
        ends_on: props.campaign?.endsOn ?? '',
        priority: props.campaign?.priority ?? 'NORMAL',
        is_active: props.campaign?.isActive ?? true,
    });
});

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    if (props.campaign) form.put(routes.contentCampaign(props.brandId, props.campaign.id), options);
    else form.post(routes.contentCampaigns(props.brandId), options);
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        :title="campaign ? `Ubah kampanye ${campaign.name}` : 'Tambah kampanye'"
        description="Periode dihitung per hari WIB, termasuk tanggal mulai dan selesai."
        confirm-label="Simpan kampanye"
        :submitting="form.processing"
        @confirm="submit"
    >
        <div class="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
            <FormField id="campaign-name" v-slot="{ describedBy, invalid }" label="Nama" :error="form.errors.name">
                <Input id="campaign-name" v-model="form.name" class="h-11" maxlength="120" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
            <FormField id="campaign-objective" v-slot="{ describedBy, invalid }" label="Tujuan" :error="form.errors.objective">
                <Textarea
                    id="campaign-objective"
                    v-model="form.objective"
                    :rows="2"
                    :maxlength="2000"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField id="campaign-start" v-slot="{ describedBy, invalid }" label="Mulai" :error="form.errors.starts_on">
                    <Input id="campaign-start" v-model="form.starts_on" type="date" class="h-11" :aria-describedby="describedBy" :aria-invalid="invalid" />
                </FormField>
                <FormField id="campaign-end" v-slot="{ describedBy, invalid }" label="Selesai" :error="form.errors.ends_on">
                    <Input id="campaign-end" v-model="form.ends_on" type="date" class="h-11" :aria-describedby="describedBy" :aria-invalid="invalid" />
                </FormField>
            </div>
            <FormField id="campaign-priority" v-slot="{ describedBy, invalid }" label="Prioritas" :error="form.errors.priority">
                <select
                    id="campaign-priority"
                    v-model="form.priority"
                    class="h-11 w-full rounded-md border border-input bg-card px-3 text-sm"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                >
                    <option v-for="priority in priorities" :key="priority" :value="priority">{{ campaignPriorities[priority] }}</option>
                </select>
            </FormField>
            <div class="flex items-center gap-2">
                <Checkbox id="campaign-active" :model-value="form.is_active" @update:model-value="form.is_active = $event === true" />
                <label for="campaign-active" class="text-sm">Aktif (dapat dipilih untuk konten baru)</label>
            </div>
        </div>
    </ConfirmDialog>
</template>
