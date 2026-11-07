<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { routes } from '@/lib/routes';
import type { ContentCampaign } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{ brandId: string; campaigns: ContentCampaign[] }>();
const open = defineModel<boolean>('open', { default: false });
const form = useForm({ statement: '', source: '', campaign: '', valid_until: '' });

watch(open, (value) => {
    if (value) {
        form.reset();
        form.clearErrors();
    }
});

function submit(): void {
    form.transform((d) => ({ ...d, campaign: d.campaign || null, valid_until: d.valid_until || null })).post(routes.contentFacts(props.brandId), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Tambah fakta disetujui"
        description="Harga, nama produk, promo, dan klaim angka di konten harus tertulis di sini. Fakta tidak bisa diubah; ganti dengan fakta baru bila berubah."
        confirm-label="Tambah fakta"
        :submitting="form.processing"
        @confirm="submit"
    >
        <div class="max-h-[60vh] space-y-4 overflow-y-auto pr-1">
            <FormField
                id="fact-statement"
                v-slot="{ describedBy, invalid }"
                label="Fakta *"
                :error="form.errors.statement"
                hint="Tulis persis, termasuk angka. Contoh: Paket Landing Page Rp 1.500.000."
            >
                <Textarea id="fact-statement" v-model="form.statement" :rows="3" :maxlength="1000" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
            <FormField
                id="fact-source"
                v-slot="{ describedBy, invalid }"
                label="Sumber *"
                :error="form.errors.source"
                hint="Misalnya daftar harga, kontrak, atau persetujuan pemilik."
            >
                <Input id="fact-source" v-model="form.source" class="h-11" maxlength="255" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField id="fact-campaign" v-slot="{ describedBy, invalid }" label="Khusus kampanye" :error="form.errors.campaign">
                    <select
                        id="fact-campaign"
                        v-model="form.campaign"
                        class="h-11 w-full rounded-md border border-input bg-card px-3 text-sm"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    >
                        <option value="">Semua konten brand</option>
                        <option v-for="campaign in campaigns" :key="campaign.id" :value="campaign.id">{{ campaign.name }}</option>
                    </select>
                </FormField>
                <FormField
                    id="fact-valid"
                    v-slot="{ describedBy, invalid }"
                    label="Berlaku sampai"
                    :error="form.errors.valid_until"
                    hint="Kosongkan bila tidak ada batas."
                >
                    <Input id="fact-valid" v-model="form.valid_until" type="date" class="h-11" :aria-describedby="describedBy" :aria-invalid="invalid" />
                </FormField>
            </div>
        </div>
    </ConfirmDialog>
</template>
