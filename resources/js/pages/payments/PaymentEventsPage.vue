<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PaymentEventsView from '@/features/payments/PaymentEventsView.vue';
import { routes } from '@/lib/routes';
import type { Paginated, PaymentEventFilters, WebhookEventRow } from '@/types/backoffice';

// The old Event Pembayaran page, kept during the ADR-0010 transition. The same view lives at Transaksi → Event Pembayaran.
defineProps<{ filters: PaymentEventFilters; events: Paginated<WebhookEventRow> }>();
</script>

<template>
    <AppLayout title="Event Pembayaran" description="Bukti notifikasi gateway yang sudah disaring">
        <PaymentEventsView :filters="filters" :events="events" :path="routes.paymentEvents" />
    </AppLayout>
</template>
