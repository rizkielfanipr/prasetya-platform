<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import PasswordInput from '@/components/shared/PasswordInput.vue';
import { routes } from '@/lib/routes';
import type { UserRow } from '@/types/backoffice';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ user: UserRow }>();
const open = defineModel<boolean>('open', { default: false });
const form = useForm({ password: '', password_confirmation: '' });

function submit(): void {
    form.put(routes.userPassword(props.user.id), {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        :title="`Atur kata sandi ${user.username}`"
        description="Pengguna ini langsung keluar dari semua perangkat dan harus masuk dengan kata sandi baru. Sampaikan kata sandinya secara pribadi."
        confirm-label="Atur kata sandi"
        :submitting="form.processing"
        @confirm="submit"
    >
        <div class="space-y-4">
            <FormField
                :id="`reset-password-${user.id}`"
                v-slot="{ describedBy, invalid }"
                label="Kata sandi baru"
                :error="form.errors.password"
                hint="Minimal 12 karakter, berisi huruf dan angka."
            >
                <PasswordInput
                    :id="`reset-password-${user.id}`"
                    v-model="form.password"
                    autocomplete="new-password"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField :id="`reset-password-confirmation-${user.id}`" label="Ulangi kata sandi baru">
                <PasswordInput :id="`reset-password-confirmation-${user.id}`" v-model="form.password_confirmation" autocomplete="new-password" />
            </FormField>
        </div>
    </ConfirmDialog>
</template>
