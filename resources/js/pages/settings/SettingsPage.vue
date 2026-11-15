<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import AdminPanels from '@/features/settings/AdminPanels.vue';
import type { SettingsAdmin } from '@/types/backoffice';
import { routes } from '@/lib/routes';
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';

defineProps<{ admin: SettingsAdmin }>();
</script>

<template>
    <AppLayout title="Pengaturan" description="Konfigurasi platform dan aktivitas sistem. Kata sandi Anda diganti dari menu akun.">
        <AdminPanels :admin="admin" />
        <PanelCard title="Aktivitas sistem" description="Masuk, keluar, perubahan pengaturan, dan aktivitas di luar transaksi dan pengelolaan pengguna.">
            <Link :href="routes.settingsActivity" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-text hover:underline"
                >Buka aktivitas sistem <ArrowRight class="size-4" aria-hidden="true"
            /></Link>
        </PanelCard>
        <PanelCard title="Log gateway" description="Setiap notifikasi webhook dari gateway, untuk menelusuri masalah pembayaran.">
            <Link
                :href="routes.gatewayLog()"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-text hover:underline"
                data-testid="open-gateway-log"
                >Buka log gateway <ArrowRight class="size-4" aria-hidden="true"
            /></Link>
        </PanelCard>
    </AppLayout>
</template>
