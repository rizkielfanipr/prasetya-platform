<script setup lang="ts">
import { paymentStatuses } from '@/lib/status';
import type { PaymentStatus } from '@/types/backoffice';
import StatusBadge from './StatusBadge.vue';

defineProps<{ status: PaymentStatus | null }>();
</script>

<template>
    <StatusBadge v-if="status" :status="paymentStatuses[status]" />
    <span v-else class="text-xs font-medium text-muted-foreground">Belum dibayar</span>
</template>
