<script setup lang="ts">
import PaymentSummary from '@/components/shared/PaymentSummary.vue';
import PaymentTimeline from '@/components/shared/PaymentTimeline.vue';
import GatewayCheckDialog from '@/features/reconciliation/GatewayCheckDialog.vue';
import { formatDateTime } from '@/lib/format';
import type { PaymentAttempt } from '@/types/backoffice';

defineProps<{ payment: PaymentAttempt }>();
</script>

<template>
    <article :aria-labelledby="`attempt-${payment.id}`" class="border-t py-5 first:border-t-0 first:pt-0 last:pb-0">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h3 :id="`attempt-${payment.id}`" class="text-base font-bold">Percobaan {{ payment.attempt }}</h3>
            <!-- Same POST as before: CheckPaymentWithGateway decides; nothing here sets a status (ADR-0010). -->
            <GatewayCheckDialog v-if="payment.checkable" :payment-id="payment.id" :invoice="payment.gatewayInvoice" />
        </div>
        <PaymentSummary :payment="payment" />
        <p v-if="payment.lastGatewayCheck" class="mt-3 text-xs text-muted-foreground" data-testid="last-gateway-check">
            Cek status terakhir: <time :datetime="payment.lastGatewayCheck.at">{{ formatDateTime(payment.lastGatewayCheck.at) }}</time> · status gateway
            {{ payment.lastGatewayCheck.gatewayStatus ?? 'tidak ditemukan' }}
        </p>
        <details class="group mt-4">
            <summary class="cursor-pointer text-sm font-semibold text-primary-text hover:underline">Kejadian ({{ payment.events.length }})</summary>
            <div class="mt-3"><PaymentTimeline :events="payment.events" /></div>
        </details>
    </article>
</template>
