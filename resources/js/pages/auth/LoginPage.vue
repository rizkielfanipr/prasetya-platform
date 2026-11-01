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
    <AuthLayout title="Masuk">
        <p v-if="status" role="status" class="mb-4 rounded-md bg-success-surface px-3 py-2 text-sm font-medium text-success">{{ status }}</p>
        <p v-if="sessionMessage" role="alert" class="mb-4 rounded-md bg-warning-surface px-3 py-2 text-sm font-medium text-warning">{{ sessionMessage }}</p>
        <LoginForm />
    </AuthLayout>
</template>
