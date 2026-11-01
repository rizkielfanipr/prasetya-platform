<script setup lang="ts">
import PanelCard from '@/components/layout/PanelCard.vue';
import FormField from '@/components/shared/FormField.vue';
import PasswordInput from '@/components/shared/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { copy } from '@/lib/copy';
import { routes } from '@/lib/routes';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ current_password: '', password: '', password_confirmation: '' });

function submit(): void {
    form.put(routes.settingsPassword, { preserveScroll: true, onFinish: () => form.reset() });
}
</script>

<template>
    <PanelCard title="Kata sandi">
        <form class="space-y-4" novalidate @submit.prevent="submit">
            <FormField id="current_password" v-slot="{ describedBy, invalid }" label="Kata sandi saat ini" :error="form.errors.current_password">
                <PasswordInput
                    id="current_password"
                    v-model="form.current_password"
                    autocomplete="current-password"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField
                id="new_password"
                v-slot="{ describedBy, invalid }"
                label="Kata sandi baru"
                :error="form.errors.password"
                hint="Minimal 12 karakter, berisi huruf dan angka."
            >
                <PasswordInput id="new_password" v-model="form.password" autocomplete="new-password" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
            <FormField id="new_password_confirmation" label="Ulangi kata sandi baru">
                <PasswordInput id="new_password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" />
            </FormField>
            <Button type="submit" :disabled="form.processing">{{ form.processing ? copy.saving : copy.save }}</Button>
        </form>
    </PanelCard>
</template>
