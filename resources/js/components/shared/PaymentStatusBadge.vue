<script setup lang="ts">
import { noPaymentLabel, paymentStatuses } from '@/lib/status';
import type { PaymentStatus } from '@/types/backoffice';
import StatusBadge from './StatusBadge.vue';

defineProps<{ status: PaymentStatus | null }>();
</script>

<template>
    <StatusBadge v-if="status" :status="paymentStatuses[status]" />
    <span v-else class="text-xs font-semibold text-muted-foreground">{{ noPaymentLabel }}</span>
</template>
