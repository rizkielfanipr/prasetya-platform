<script setup lang="ts">
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { contentStatuses } from '@/lib/content';
import type { ContentStatus } from '@/types/content';

defineProps<{ status: ContentStatus }>();
</script>

<template>
    <StatusBadge :status="contentStatuses[status]" />
</template>
