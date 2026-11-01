<script setup lang="ts">
import { type StatusDefinition, toneClasses } from '@/lib/status';

defineProps<{ status: StatusDefinition }>();
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap" :class="toneClasses[status.tone]">
        <component :is="status.icon" class="size-3.5 shrink-0" aria-hidden="true" />
        {{ status.label }}
    </span>
</template>
