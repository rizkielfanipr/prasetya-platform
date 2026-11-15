<script setup lang="ts">
import { contentSections } from '@/lib/content';
import { formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { SharedProps } from '@/types/backoffice';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Content Studio's sections (ADR-0017), drawn as the workspace tabs of Transaksi (DESIGN.md, Tabs). Insight is listed
 * once a reading exists, or while it is the page shown; the AI model lives in Pengaturan.
 */
const props = defineProps<{ current: (typeof contentSections)[number]['value'] }>();
const hrefs = {
    calendar: routes.contentCalendar,
    insights: routes.contentInsights,
    brands: routes.contentBrands,
} as const;
const page = usePage<SharedProps>();
const due = computed(() => page.props.contentDueCount ?? 0);
const sections = computed(() =>
    contentSections.filter((section) => section.value !== 'insights' || page.props.contentHasInsights || props.current === 'insights'),
);
</script>

<template>
    <nav
        aria-label="Bagian Content Studio"
        class="flex max-w-full gap-6 overflow-x-auto border-b border-border max-sm:[mask-image:linear-gradient(to_right,black_82%,transparent)] max-sm:pr-10"
    >
        <Link
            v-for="section in sections"
            :key="section.value"
            :href="hrefs[section.value]"
            class="-mb-px inline-flex h-11 shrink-0 items-center gap-2 border-b-[3px] text-sm font-bold whitespace-nowrap transition-colors hover:text-foreground focus-visible:rounded-t-md motion-reduce:transition-none"
            :class="section.value === current ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:border-tint-300'"
            :aria-current="section.value === current ? 'page' : undefined"
            :data-testid="`content-section-${section.value}`"
        >
            {{ section.label }}
            <span
                v-if="section.value === 'calendar' && due > 0"
                class="rounded-md bg-warning-surface px-1.5 text-[11px] leading-5 font-extrabold text-warning tabular"
                :aria-label="`${due} konten perlu diposting`"
                >{{ formatNumber(due) }}</span
            >
        </Link>
    </nav>
</template>
