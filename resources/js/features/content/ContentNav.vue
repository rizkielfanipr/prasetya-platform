<script setup lang="ts">
import { contentSections } from '@/lib/content';
import { routes } from '@/lib/routes';
import { Link } from '@inertiajs/vue3';

defineProps<{ current: (typeof contentSections)[number]['value'] }>();
const hrefs = { calendar: routes.contentCalendar, items: routes.contentItems } as const;
</script>

<template>
    <nav aria-label="Bagian Content Studio" class="flex max-w-full gap-1 overflow-x-auto border-b">
        <Link
            v-for="section in contentSections"
            :key="section.value"
            :href="hrefs[section.value]"
            class="-mb-px inline-flex h-11 shrink-0 items-center border-b-2 px-3 text-sm whitespace-nowrap transition-colors hover:text-foreground focus-visible:rounded-t-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none"
            :class="section.value === current ? 'border-primary-strong font-semibold text-foreground' : 'border-transparent font-medium text-muted-foreground'"
            :aria-current="section.value === current ? 'page' : undefined"
            >{{ section.label }}</Link
        >
    </nav>
</template>
