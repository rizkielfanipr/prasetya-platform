<script setup lang="ts">
import AuthLayout from '@/components/layout/AuthLayout.vue';
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { routes } from '@/lib/routes';
import { Link, useForm } from '@inertiajs/vue3';

defineProps<{ status?: string | null }>();
const form = useForm({ email: '' });
</script>

<template>
    <AuthLayout title="Lupa kata sandi">
        <p v-if="status" role="status" class="mb-4 rounded-md bg-success-surface px-3 py-2 text-sm font-medium text-success">{{ status }}</p>
        <form class="space-y-4" novalidate @submit.prevent="form.post(routes.forgotPassword)">
            <FormField
                id="email"
                v-slot="{ describedBy, invalid }"
                label="Email"
                :error="form.errors.email"
                hint="Kami kirim tautan untuk mengatur ulang kata sandi."
            >
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
            <Button type="submit" class="h-12 w-full text-base font-semibold" :disabled="form.processing">{{
                form.processing ? 'Mengirim…' : 'Kirim tautan'
            }}</Button>
            <Link :href="routes.login" class="block text-center text-sm font-medium text-primary-text hover:underline">Kembali ke halaman masuk</Link>
        </form>
    </AuthLayout>
</template>
