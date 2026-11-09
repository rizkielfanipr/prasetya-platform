<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <div data-slot="alert-title" :class="cn('col-start-2 font-semibold leading-none tracking-tight', props.class)"><slot /></div>
</template>
