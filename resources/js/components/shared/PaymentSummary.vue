<script setup lang="ts">
import { formatCurrency, formatDateTime } from '@/lib/format';
import type { PaymentAttempt } from '@/types/backoffice';
import PaymentStatusBadge from './PaymentStatusBadge.vue';

defineProps<{ payment: PaymentAttempt }>();
</script>

<template>
    <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-3">
        <div>
            <dt class="text-xs font-semibold text-muted-foreground">Status</dt>
            <dd class="mt-1"><PaymentStatusBadge :status="payment.status" /></dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-muted-foreground">Jumlah</dt>
            <dd class="mt-1 font-semibold tabular">{{ formatCurrency(payment.amount) }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-muted-foreground">Metode</dt>
            <dd class="mt-1">{{ payment.paymentMethod ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-muted-foreground">Invoice gateway</dt>
            <dd class="mt-1 break-all">{{ payment.gatewayInvoice }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-muted-foreground">Referensi {{ payment.provider }}</dt>
            <dd class="mt-1 break-all">{{ payment.gatewayReference ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-muted-foreground">Dibayar</dt>
            <dd class="mt-1">{{ formatDateTime(payment.paidAt) }}</dd>
        </div>
        <div>
            <dt class="text-xs font-semibold text-muted-foreground">Berlaku sampai</dt>
            <dd class="mt-1">{{ formatDateTime(payment.expiresAt) }}</dd>
        </div>
        <div v-if="payment.failureCode">
            <dt class="text-xs font-semibold text-muted-foreground">Kode kegagalan</dt>
            <dd class="mt-1 break-all">{{ payment.failureCode }}</dd>
        </div>
    </dl>
</template>
