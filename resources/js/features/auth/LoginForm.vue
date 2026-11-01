<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import PasswordInput from '@/components/shared/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { routes } from '@/lib/routes';
import { Link, useForm } from '@inertiajs/vue3';

const form = useForm({ email: '', password: '', remember: false });

function submit(): void {
    form.post(routes.login, { onFinish: () => form.reset('password') });
}
</script>

<template>
    <form class="space-y-4" novalidate @submit.prevent="submit">
        <FormField id="email" v-slot="{ describedBy, invalid }" label="Email" :error="form.errors.email">
            <Input
                id="email"
                v-model="form.email"
                type="email"
                class="h-12"
                placeholder="nama@perusahaan.com"
                autocomplete="username"
                required
                autofocus
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>
        <FormField id="password" v-slot="{ describedBy, invalid }" label="Kata sandi" :error="form.errors.password">
            <PasswordInput
                id="password"
                v-model="form.password"
                autocomplete="current-password"
                required
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <Checkbox id="remember" v-model="form.remember" class="border-input-strong" />
                <Label for="remember" class="font-normal">Ingat saya</Label>
            </div>
            <Link :href="routes.forgotPassword" class="text-sm font-medium text-primary-text hover:underline">Lupa kata sandi?</Link>
        </div>
        <Button type="submit" class="h-12 w-full rounded-md text-base font-semibold" :disabled="form.processing" :aria-busy="form.processing">
            {{ form.processing ? 'Memeriksa…' : 'Masuk' }}
        </Button>
    </form>
</template>
