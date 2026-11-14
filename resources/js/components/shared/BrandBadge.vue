<script setup lang="ts">
import { brands } from '@/lib/brands';
import type { Brand } from '@/types/backoffice';

/** The brand as a line bullet: its code on the brand's own colour, then its name. */
withDefaults(defineProps<{ brand: Brand; compact?: boolean }>(), { compact: false });
</script>

<template>
    <span class="inline-flex items-center gap-2 text-[13px] font-bold whitespace-nowrap text-foreground">
        <span
            class="inline-flex h-[22px] min-w-[28px] items-center justify-center rounded-[6px] px-1 font-mono text-[11px] font-semibold"
            :class="brands[brand].bulletClass"
            aria-hidden="true"
            >{{ brands[brand].code }}</span
        >
        <span class="brand-badge__label" :class="{ 'sr-only': compact }">{{ brands[brand].label }}</span>
    </span>
</template>
