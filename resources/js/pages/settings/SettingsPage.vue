<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import AdminPanels from '@/features/settings/AdminPanels.vue';
import PasswordForm from '@/features/settings/PasswordForm.vue';
import ProfileForm from '@/features/settings/ProfileForm.vue';
import type { SettingsAdmin, SharedProps } from '@/types/backoffice';
import { routes } from '@/lib/routes';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
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
        <PanelCard title="Aktivitas sistem" description="Masuk, keluar, perubahan pengaturan, dan aktivitas di luar transaksi dan pengelolaan pengguna.">
            <Link :href="routes.settingsActivity" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-text hover:underline"
                >Buka aktivitas sistem <ArrowRight class="size-4" aria-hidden="true"
            /></Link>
        </PanelCard>
    </AppLayout>
</template>
