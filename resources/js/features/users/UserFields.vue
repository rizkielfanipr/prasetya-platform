<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import PasswordInput from '@/components/shared/PasswordInput.vue';
import { Input } from '@/components/ui/input';

/** Fields for a new user. Presentational: values come in through v-model, server errors through `errors`. */
defineProps<{ errors: Partial<Record<'name' | 'username' | 'password', string>> }>();
const name = defineModel<string>('name', { required: true });
const username = defineModel<string>('username', { required: true });
const password = defineModel<string>('password', { required: true });
const confirmation = defineModel<string>('confirmation', { required: true });
</script>

<template>
    <div class="space-y-4">
        <FormField id="new-user-name" v-slot="{ describedBy, invalid }" label="Nama" :error="errors.name">
            <Input id="new-user-name" v-model="name" class="h-11" autocomplete="off" maxlength="255" :aria-describedby="describedBy" :aria-invalid="invalid" />
        </FormField>
        <FormField
            id="new-user-username"
            v-slot="{ describedBy, invalid }"
            label="Username"
            :error="errors.username"
            hint="3–32 karakter: huruf kecil, angka, titik, garis bawah, atau tanda hubung. Tidak dapat diubah setelah dibuat."
        >
            <Input
                id="new-user-username"
                v-model="username"
                class="h-11"
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
                maxlength="32"
                :aria-describedby="describedBy"
                :aria-invalid="invalid"
            />
        </FormField>
        <FormField
            id="new-user-password"
            v-slot="{ describedBy, invalid }"
            label="Kata sandi awal"
            :error="errors.password"
            hint="Minimal 12 karakter, berisi huruf dan angka. Sampaikan ke pengguna secara pribadi."
        >
            <PasswordInput id="new-user-password" v-model="password" autocomplete="new-password" :aria-describedby="describedBy" :aria-invalid="invalid" />
        </FormField>
        <FormField id="new-user-password-confirmation" label="Ulangi kata sandi">
            <PasswordInput id="new-user-password-confirmation" v-model="confirmation" autocomplete="new-password" />
        </FormField>
    </div>
</template>
