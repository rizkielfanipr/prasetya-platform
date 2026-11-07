<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import AdminPanels from '@/features/settings/AdminPanels.vue';
import PasswordForm from '@/features/settings/PasswordForm.vue';
import ProfileForm from '@/features/settings/ProfileForm.vue';
import type { SettingsAdmin, SharedProps } from '@/types/backoffice';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ admin: SettingsAdmin }>();
const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <AppLayout title="Pengaturan" description="Profil Anda dan konfigurasi platform">
        <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
            <ProfileForm v-if="user" :user="user" />
            <PasswordForm />
        </div>
        <AdminPanels :admin="admin" />
    </AppLayout>
</template>
