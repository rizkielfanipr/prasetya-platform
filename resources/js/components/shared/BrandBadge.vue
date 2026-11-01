<script setup lang="ts">
import { brands } from '@/lib/brands';
import type { Brand } from '@/types/backoffice';

defineProps<{ brand: Brand }>();
</script>

<template>
    <span class="inline-flex items-center rounded-sm px-2.5 py-1 text-xs font-semibold whitespace-nowrap" :class="brands[brand].badgeClass">
        {{ brands[brand].label }}
    </span>
</template>
