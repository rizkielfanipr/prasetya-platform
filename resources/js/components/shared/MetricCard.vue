<script setup lang="ts">
import { formatChange } from '@/lib/format';
import { ArrowDownRight, ArrowRight, ArrowUpRight, type LucideIcon } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{
    label: string;
    value: string;
    icon: LucideIcon;
    tone: 'primary' | 'success' | 'warning' | 'danger';
    current?: number;
    previous?: number;
    comparisonLabel?: string;
}>();

const change = computed(() => (props.current !== undefined && props.previous !== undefined ? formatChange(props.current, props.previous) : null));
const iconSurface = {
    primary: 'bg-secondary text-primary-text',
    success: 'bg-success-surface text-success',
    warning: 'bg-warning-surface text-warning',
    danger: 'bg-danger-surface text-danger',
};
const changeIcon = { up: ArrowUpRight, down: ArrowDownRight, flat: ArrowRight };
</script>

<template>
    <article class="flex gap-4 rounded-xl border bg-card p-5">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-full" :class="iconSurface[tone]">
            <component :is="icon" class="size-5" aria-hidden="true" />
        </span>
        <div class="min-w-0">
            <h3 class="font-sans text-sm font-medium text-muted-foreground">{{ label }}</h3>
            <p class="mt-0.5 font-heading text-[28px] leading-tight font-bold break-words tabular" data-testid="metric-value">{{ value }}</p>
            <p v-if="change && comparisonLabel" class="mt-1.5 flex flex-wrap items-center gap-x-1.5 text-xs text-muted-foreground">
                <span class="inline-flex items-center gap-0.5 font-semibold text-foreground">
                    <component :is="changeIcon[change.direction]" class="size-3.5" aria-hidden="true" />
                    {{ change.label }}
                </span>
                {{ comparisonLabel }}
            </p>
        </div>
    </article>
</template>
