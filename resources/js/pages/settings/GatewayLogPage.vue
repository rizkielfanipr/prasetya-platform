<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import GatewayLogView from '@/features/payments/GatewayLogView.vue';
import { routes } from '@/lib/routes';
import type { Paginated, PaymentEventFilters, WebhookEventRow } from '@/types/backoffice';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';

// Pengaturan → Log Gateway (ADR-0016): the technical record of every webhook. Daily review is Transaksi → Perlu Ditinjau.
defineProps<{ filters: PaymentEventFilters; events: Paginated<WebhookEventRow> }>();
</script>

<template>
    <AppLayout
        title="Log Gateway"
        description="Setiap notifikasi webhook dari gateway, termasuk yang ditolak, duplikat, atau untuk invoice yang tidak dikenal. Untuk menelusuri masalah, bukan untuk dicek harian."
    >
        <Link :href="routes.settings" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-text hover:underline">
            <ArrowLeftIcon class="size-4 stroke-2" aria-hidden="true" />Kembali ke pengaturan
        </Link>
        <GatewayLogView :filters="filters" :events="events" :path="routes.gatewayLog()" />
    </AppLayout>
</template>
