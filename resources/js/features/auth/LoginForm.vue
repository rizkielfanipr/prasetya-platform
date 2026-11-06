<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import PasswordInput from '@/components/shared/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { routes } from '@/lib/routes';
import { useForm } from '@inertiajs/vue3';

const form = useForm({ username: '', password: '', remember: false });

function submit(): void {
    form.post(routes.login, { onFinish: () => form.reset('password') });
}
</script>

<template>
    <form class="space-y-4" novalidate @submit.prevent="submit">
        <FormField id="username" v-slot="{ describedBy, invalid }" label="Username" :error="form.errors.username">
            <Input
                id="username"
                v-model="form.username"
                class="h-12"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
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
            <!-- No email reset (ADR-0009): another user sets a new password in Pengguna. -->
            <p class="text-sm text-muted-foreground">Lupa kata sandi? Hubungi admin lain.</p>
        </div>
        <Button type="submit" class="h-12 w-full rounded-md text-base font-semibold" :disabled="form.processing" :aria-busy="form.processing">
            {{ form.processing ? 'Memeriksa…' : 'Masuk' }}
        </Button>
    </form>
</template>
