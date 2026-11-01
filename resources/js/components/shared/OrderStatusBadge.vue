<script setup lang="ts">
import { orderStatuses } from '@/lib/status';
import type { OrderStatus } from '@/types/backoffice';
import StatusBadge from './StatusBadge.vue';

defineProps<{ status: OrderStatus }>();
</script>

<template>
    <StatusBadge :status="orderStatuses[status]" />
</template>
