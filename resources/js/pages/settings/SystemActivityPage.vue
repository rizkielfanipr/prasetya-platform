<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import AuditLogView from '@/features/audit/AuditLogView.vue';
import { routes } from '@/lib/routes';
import type { AuditEntry, AuditFilters, Paginated } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

defineProps<{ filters: AuditFilters; logs: Paginated<AuditEntry> }>();
</script>

<template>
    <AppLayout
        title="Aktivitas Sistem"
        description="Masuk, keluar, perubahan pengaturan, dan aktivitas lain di luar transaksi dan pengelolaan pengguna. Catatan tidak dapat diubah."
    >
        <Link
            :href="routes.settings"
            class="focus-visible:outline-line-orange inline-flex items-center gap-1.5 text-[13px] font-semibold text-primary-text underline decoration-tint-300 underline-offset-4 hover:decoration-tint-600 focus-visible:outline-2 focus-visible:outline-offset-2"
        >
            <ArrowLeftIcon class="size-4 stroke-2" aria-hidden="true" />Kembali ke pengaturan
        </Link>
        <p class="max-w-3xl text-[13px] leading-relaxed text-muted-foreground">
            Aktivitas transaksi ada di Riwayat pada detail transaksi, dan aktivitas pengguna di detail pengguna. Pilih lingkup
            <span class="text-sign-ink font-semibold">Semua audit</span> untuk mencari di seluruh catatan.
        </p>
        <AuditLogView :filters="filters" :logs="logs" :path="routes.settingsActivity" with-scope />
    </AppLayout>
</template>
