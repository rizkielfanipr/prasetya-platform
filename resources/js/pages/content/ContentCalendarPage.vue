<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import ErrorState from '@/components/shared/ErrorState.vue';
import FilterBar, { type ActiveFilter } from '@/components/shared/FilterBar.vue';
import { buttonVariants } from '@/components/ui/button';
import { useUrlFilters } from '@/composables/useUrlFilters';
import ContentCreateDialog from '@/features/content/ContentCreateDialog.vue';
import ContentFilterFields from '@/features/content/ContentFilterFields.vue';
import ContentNav from '@/features/content/ContentNav.vue';
import ContentWeekView from '@/features/content/ContentWeekView.vue';
import { contentStatuses } from '@/lib/content';
import { formatDate } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ContentBrand, ContentFilters, ContentRow, ContentWeek } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed } from 'vue';

/** Kalender (ADR-0011): one week of planned posts. A planned time passing changes nothing. */
const props = defineProps<{ filters: ContentFilters & { week: string }; week: ContentWeek; items: ContentRow[]; brands: ContentBrand[] }>();

const { apply, loading, failed } = useUrlFilters(
    () => routes.contentCalendar,
    () => ({ ...props.filters }),
    ['filters', 'week', 'items'],
);

const query = (week: string) => Object.fromEntries(Object.entries({ ...props.filters, week }).filter(([, value]) => value !== null));
const brandName = (code: string) => props.brands.find((brand) => brand.code === code)?.name ?? code;
const active = computed<ActiveFilter[]>(() =>
    [
        props.filters.brand ? { key: 'brand', label: `Brand: ${brandName(props.filters.brand)}` } : null,
        props.filters.status ? { key: 'status', label: `Status: ${contentStatuses[props.filters.status].label}` } : null,
    ].filter((filter): filter is ActiveFilter => filter !== null),
);
const isCurrentWeek = computed(() => props.week.days.includes(props.week.today));
const range = computed(() => `${formatDate(`${props.week.start}T00:00:00+07:00`)} – ${formatDate(`${props.week.end}T00:00:00+07:00`)}`);
const navClass = buttonVariants({ variant: 'outline', size: 'sm' });
</script>

<template>
    <AppLayout title="Content Studio" description="Rencanakan, tinjau, dan catat posting Instagram. Posting tetap dilakukan manual.">
        <ContentNav current="calendar" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="routes.contentCalendar" :data="query(week.previous)" :class="navClass" aria-label="Minggu sebelumnya" preserve-scroll
                    ><ChevronLeft class="size-4" aria-hidden="true"
                /></Link>
                <h2 class="min-w-52 text-center text-base font-semibold" aria-live="polite">{{ range }}</h2>
                <Link :href="routes.contentCalendar" :data="query(week.next)" :class="navClass" aria-label="Minggu berikutnya" preserve-scroll
                    ><ChevronRight class="size-4" aria-hidden="true"
                /></Link>
                <Link v-if="!isCurrentWeek" :href="routes.contentCalendar" :data="query(week.today)" :class="navClass">Minggu ini</Link>
            </div>
            <ContentCreateDialog :brands="brands" :default-day="isCurrentWeek ? week.today : week.start" />
        </div>

        <FilterBar :active="active" @remove="apply({ [$event]: null })" @clear="apply({ brand: null, status: null })">
            <ContentFilterFields :filters="filters" :brands="brands" @apply="apply" />
        </FilterBar>

        <ErrorState v-if="failed" retryable @retry="apply({})" />
        <ContentWeekView v-else :week="week" :items="items" :loading="loading" />
    </AppLayout>
</template>
