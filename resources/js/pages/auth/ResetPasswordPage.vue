<script setup lang="ts">
import AuthLayout from '@/components/layout/AuthLayout.vue';
import FormField from '@/components/shared/FormField.vue';
import PasswordInput from '@/components/shared/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { routes } from '@/lib/routes';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ token: string; email: string }>();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
</script>

<template>
    <AuthLayout title="Atur ulang kata sandi">
        <form
            class="space-y-4"
            novalidate
            @submit.prevent="form.post(routes.resetPassword, { onFinish: () => form.reset('password', 'password_confirmation') })"
        >
            <FormField id="email" v-slot="{ describedBy, invalid }" label="Email" :error="form.errors.email">
                <Input
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="h-12"
                    autocomplete="username"
                    required
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField
                id="password"
                v-slot="{ describedBy, invalid }"
                label="Kata sandi baru"
                :error="form.errors.password"
                hint="Minimal 12 karakter, berisi huruf dan angka."
            >
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    autocomplete="new-password"
                    required
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                />
            </FormField>
            <FormField id="password_confirmation" label="Ulangi kata sandi baru">
                <PasswordInput id="password_confirmation" v-model="form.password_confirmation" autocomplete="new-password" required />
            </FormField>
            <Button type="submit" class="h-12 w-full text-base font-semibold" :disabled="form.processing">{{
                form.processing ? 'Menyimpan…' : 'Simpan kata sandi'
            }}</Button>
        </form>
    </AuthLayout>
</template>
