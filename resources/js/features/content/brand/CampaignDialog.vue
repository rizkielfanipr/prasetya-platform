<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { routes } from '@/lib/routes';
import type { ContentCampaign } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

/**
 * A campaign (ADR-0012, phase 4): name, period in WIB days, and objective. Whether it runs follows from the period.
 * Priority is legacy and kept as it is. A campaign switched off by hand in the old workflow shows that, with a way to
 * switch it back on; new campaigns never need the switch.
 */
const props = defineProps<{ brandId: string; campaign: ContentCampaign | null }>();
const open = defineModel<boolean>('open', { default: false });
const form = useForm({ name: '', starts_on: '', ends_on: '', objective: '', is_active: true });
const legacyInactive = computed(() => props.campaign !== null && !props.campaign.isActive);

watch(open, (value) => {
    if (!value) return;
    form.clearErrors();
    Object.assign(form, {
        name: props.campaign?.name ?? '',
        objective: props.campaign?.objective ?? '',
        starts_on: props.campaign?.startsOn ?? '',
        ends_on: props.campaign?.endsOn ?? '',
        is_active: props.campaign?.isActive ?? true,
    });
});

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };
    // Only a campaign switched off by hand sends the switch; otherwise the server keeps it.
    const send = form.transform(({ is_active, ...data }) => (legacyInactive.value ? { ...data, is_active } : data));
    if (props.campaign) send.put(routes.contentCampaign(props.brandId, props.campaign.id), options);
    else send.post(routes.contentCampaigns(props.brandId), options);
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        :title="campaign ? `Ubah campaign ${campaign.name}` : 'Tambah campaign'"
        description="Campaign berjalan dari tanggal mulai sampai tanggal selesai (WIB), termasuk keduanya. Hanya campaign yang berjalan ditawarkan untuk konten baru."
        confirm-label="Simpan campaign"
        :submitting="form.processing"
        @confirm="submit"
    >
        <div class="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
            <FormField id="campaign-name" v-slot="{ describedBy, invalid }" label="Nama *" :error="form.errors.name">
                <Input id="campaign-name" v-model="form.name" class="h-11" maxlength="120" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField id="campaign-start" v-slot="{ describedBy, invalid }" label="Tanggal mulai *" :error="form.errors.starts_on">
                    <Input id="campaign-start" v-model="form.starts_on" type="date" class="h-11" :aria-describedby="describedBy" :aria-invalid="invalid" />
                </FormField>
                <FormField id="campaign-end" v-slot="{ describedBy, invalid }" label="Tanggal selesai *" :error="form.errors.ends_on">
                    <Input id="campaign-end" v-model="form.ends_on" type="date" class="h-11" :aria-describedby="describedBy" :aria-invalid="invalid" />
                </FormField>
            </div>
            <FormField
                id="campaign-objective"
                v-slot="{ describedBy, invalid }"
                label="Tujuan"
                :error="form.errors.objective"
                hint="Opsional. Dipakai AI sebagai konteks."
            >
                <Textarea
                    id="campaign-objective"
                    v-model="form.objective"
                    :rows="2"
                    :maxlength="2000"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <div v-if="legacyInactive" class="space-y-2 rounded-lg bg-muted p-3" data-testid="campaign-legacy-inactive">
                <p class="text-sm">Campaign ini dinonaktifkan manual di alur lama, sehingga tidak ditawarkan walau periodenya berjalan.</p>
                <div class="flex items-center gap-2">
                    <Checkbox id="campaign-active" :model-value="form.is_active" @update:model-value="form.is_active = $event === true" />
                    <label for="campaign-active" class="text-sm">Aktifkan lagi</label>
                </div>
            </div>
        </div>
    </ConfirmDialog>
</template>
