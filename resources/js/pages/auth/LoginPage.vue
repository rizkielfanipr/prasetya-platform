<script setup lang="ts">
import AuthLayout from '@/components/layout/AuthLayout.vue';
import LoginForm from '@/features/auth/LoginForm.vue';
import type { SharedProps } from '@/types/backoffice';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ status?: string | null }>();
const page = usePage<SharedProps>();
const sessionMessage = computed(() => page.props.flash.error);
</script>

<template>
    <AuthLayout title="Masuk" heading="Masuk" description="Gunakan username dan kata sandi akun back office Anda.">
        <p v-if="status" role="status" class="mb-5 rounded-md bg-success-surface px-3 py-2.5 text-sm font-semibold text-success">{{ status }}</p>
        <p v-if="sessionMessage" role="alert" class="mb-5 rounded-md bg-warning-surface px-3 py-2.5 text-sm font-semibold text-warning">{{ sessionMessage }}</p>
        <LoginForm />
    </AuthLayout>
</template>
