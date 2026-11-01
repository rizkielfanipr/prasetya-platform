<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { routes } from '@/lib/routes';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ paymentId: string; invoice: string | null }>();
const open = ref(false);
const form = useForm({ reason: '' });

function submit(): void {
    form.post(routes.gatewayCheck(props.paymentId), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
    });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Periksa status ke gateway?"
        :description="`Platform akan menanyakan status ${invoice ?? 'pembayaran ini'} ke gateway. Status hanya berubah jika gateway memberi bukti yang lolos verifikasi. Tindakan ini tercatat di audit log.`"
        confirm-label="Periksa sekarang"
        :submitting="form.processing"
        @confirm="submit"
    >
        <template #trigger>
            <Button variant="outline" size="sm">Cek status</Button>
        </template>
        <FormField :id="`reason-${paymentId}`" v-slot="{ describedBy, invalid }" label="Alasan (opsional)" :error="form.errors.reason">
            <Input :id="`reason-${paymentId}`" v-model="form.reason" maxlength="255" :aria-describedby="describedBy" :aria-invalid="invalid" />
        </FormField>
    </ConfirmDialog>
</template>
