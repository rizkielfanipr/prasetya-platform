<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import AuditLogView from '@/features/audit/AuditLogView.vue';
import { routes } from '@/lib/routes';
import type { AuditEntry, AuditFilters, Paginated } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

defineProps<{ filters: AuditFilters; logs: Paginated<AuditEntry> }>();
</script>

<template>
    <AppLayout
        title="Aktivitas Sistem"
        description="Masuk, keluar, perubahan pengaturan, dan aktivitas lain di luar transaksi dan pengelolaan pengguna. Catatan tidak dapat diubah."
    >
        <Link :href="routes.settings" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-text hover:underline">
            <ArrowLeft class="size-4" aria-hidden="true" />Kembali ke pengaturan
        </Link>
        <p class="text-sm text-muted-foreground">
            Aktivitas transaksi ada di detail transaksi (tab Aktivitas), dan aktivitas pengguna di detail pengguna. Pilih lingkup
            <span class="font-semibold">Semua audit</span> untuk mencari di seluruh catatan.
        </p>
        <AuditLogView :filters="filters" :logs="logs" :path="routes.settingsActivity" with-scope />
    </AppLayout>
</template>
