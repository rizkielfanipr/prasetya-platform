<script setup lang="ts">
import { type StatusDefinition, toneClasses } from '@/lib/status';

defineProps<{ status: StatusDefinition }>();
</script>

<template>
    <span class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-bold whitespace-nowrap" :class="toneClasses[status.tone]">
        <component :is="status.icon" class="size-3.5 shrink-0 stroke-2" aria-hidden="true" />
        {{ status.label }}
    </span>
</template>
