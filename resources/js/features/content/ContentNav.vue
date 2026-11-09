<script setup lang="ts">
import { contentSections } from '@/lib/content';
import { formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { SharedProps } from '@/types/backoffice';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{ current: (typeof contentSections)[number]['value'] }>();
const hrefs = {
    calendar: routes.contentCalendar,
    insights: routes.contentInsights,
    brands: routes.contentBrands,
    settings: routes.contentSettings,
} as const;
const due = computed(() => usePage<SharedProps>().props.contentDueCount ?? 0);
</script>

<template>
    <nav aria-label="Bagian Content Studio" class="flex max-w-full gap-1 overflow-x-auto border-b">
        <Link
            v-for="section in contentSections"
            :key="section.value"
            :href="hrefs[section.value]"
            class="-mb-px inline-flex h-11 shrink-0 items-center gap-2 border-b-2 px-3 text-sm whitespace-nowrap transition-colors hover:text-foreground focus-visible:rounded-t-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none"
            :class="section.value === current ? 'border-primary-strong font-semibold text-foreground' : 'border-transparent font-medium text-muted-foreground'"
            :aria-current="section.value === current ? 'page' : undefined"
        >
            {{ section.label }}
            <span
                v-if="section.value === 'calendar' && due > 0"
                class="rounded-full bg-warning-surface px-2 py-0.5 text-[11px] font-bold text-warning tabular"
                :aria-label="`${due} konten perlu diposting`"
                >{{ formatNumber(due) }}</span
            >
        </Link>
    </nav>
</template>
