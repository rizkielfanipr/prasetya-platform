<script setup lang="ts">
import PaymentRoute from '@/components/shared/PaymentRoute.vue';
import GatewayCheckDialog from '@/features/reconciliation/GatewayCheckDialog.vue';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { paymentMethodLabel } from '@/lib/payment-methods';
import type { PaymentAttempt } from '@/types/backoffice';

/**
 * One payment attempt, compact (ADR-0016). Its events are entries of the page's Riwayat, so they are not repeated
 * here. Cek status posts to CheckPaymentWithGateway; nothing here sets a status (ADR-0010).
 */
defineProps<{ payment: PaymentAttempt }>();
</script>

<template>
    <article :aria-labelledby="`attempt-${payment.id}`" class="border-t border-border py-4 first:border-t-0 first:pt-1">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <h3 :id="`attempt-${payment.id}`" class="text-[13px] font-extrabold">Percobaan {{ payment.attempt }}</h3>
                <PaymentRoute :status="payment.status" />
            </div>
            <GatewayCheckDialog v-if="payment.checkable" :payment-id="payment.id" :invoice="payment.gatewayInvoice" />
        </div>
        <dl class="mt-3 grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Jumlah</dt>
                <dd class="mt-0.5 text-sm font-semibold tabular">{{ formatCurrency(payment.amount) }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Metode</dt>
                <dd class="mt-0.5 text-sm">{{ paymentMethodLabel(payment.paymentMethod) ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">
                    {{ payment.paidAt ? 'Dibayar' : 'Berlaku sampai' }}
                </dt>
                <dd class="mt-0.5 text-sm tabular">{{ formatDateTime(payment.paidAt ?? payment.expiresAt) }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Invoice gateway</dt>
                <dd class="mt-0.5 font-mono text-sm font-semibold break-all">{{ payment.gatewayInvoice }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Referensi {{ payment.provider }}</dt>
                <dd class="mt-0.5 font-mono text-sm font-semibold break-all">{{ payment.gatewayReference ?? '—' }}</dd>
            </div>
            <div v-if="payment.failureCode">
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Kode kegagalan</dt>
                <dd class="mt-0.5 font-mono text-sm font-semibold break-all">{{ payment.failureCode }}</dd>
            </div>
        </dl>
        <p v-if="payment.lastGatewayCheck" class="mt-3 text-xs text-muted-foreground" data-testid="last-gateway-check">
            Cek status terakhir: <time :datetime="payment.lastGatewayCheck.at">{{ formatDateTime(payment.lastGatewayCheck.at) }}</time
            >, status gateway
            {{ payment.lastGatewayCheck.gatewayStatus ?? 'tidak ditemukan' }}
        </p>
    </article>
</template>
