<script setup lang="ts">
import DateRangeFilter from '@/components/shared/DateRangeFilter.vue';
import { brandCodes, brands } from '@/lib/brands';
import type { Brand, DashboardFilters } from '@/types/backoffice';
import { ChevronDownIcon } from '@heroicons/vue/24/outline';
import { computed, ref } from 'vue';

/**
 * The page's scope (DESIGN.md, Dashboard): brand and period apply to the route and the chart above everything else,
 * so they sit first, apart from the recent table's own status filter. The two-brand comparison always shows both.
 */
const props = defineProps<{ filters: DashboardFilters }>();
const emit = defineEmits<{ change: [changes: Record<string, string | null>] }>();

const brandTabs: { value: Brand | null; label: string }[] = [
    { value: null, label: 'Semua' },
    ...brandCodes.map((value) => ({ value, label: brands[value].label })),
];
const periods = [
    { value: '7', label: '7 hari terakhir' },
    { value: '30', label: '30 hari terakhir' },
    { value: '90', label: '90 hari terakhir' },
];
const customRange = computed(() => Boolean(props.filters.date_from || props.filters.date_to));
// The custom range folds into the Periode select: the date fields appear only when it is chosen or already applied.
const showCustom = ref(customRange.value);

function choosePeriod(value: string): void {
    if (value === 'custom') {
        showCustom.value = true;

        return;
    }

    showCustom.value = false;
    emit('change', { period: value, date_from: null, date_to: null });
}
</script>

<template>
    <section aria-label="Cakupan dashboard" class="flex flex-wrap items-end gap-x-3 gap-y-3">
        <div class="flex flex-col gap-1.5">
            <span aria-hidden="true" class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Brand</span>
            <div role="group" aria-label="Filter brand" class="inline-flex h-11 overflow-hidden rounded-md border border-input bg-card">
                <button
                    v-for="tab in brandTabs"
                    :key="tab.label"
                    type="button"
                    class="px-4 text-sm font-bold transition-colors duration-150 not-first:border-l not-first:border-input motion-reduce:transition-none"
                    :class="filters.brand === tab.value ? 'bg-primary-strong text-primary-foreground' : 'text-sidebar-foreground hover:bg-tint-100'"
                    :aria-pressed="filters.brand === tab.value"
                    @click="emit('change', { brand: tab.value })"
                >
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <label for="dashboard-period" class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Periode</label>
            <div class="relative">
                <select
                    id="dashboard-period"
                    class="h-11 appearance-none rounded-md border border-input bg-card pr-10 pl-3 text-sm font-semibold"
                    :value="showCustom ? 'custom' : filters.period"
                    @change="choosePeriod(($event.target as HTMLSelectElement).value)"
                >
                    <option v-for="option in periods" :key="option.value" :value="option.value">{{ option.label }}</option>
                    <option value="custom">Rentang khusus…</option>
                </select>
                <ChevronDownIcon
                    class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 stroke-2 text-muted-foreground"
                    aria-hidden="true"
                />
            </div>
        </div>

        <DateRangeFilter v-if="showCustom" :from="filters.date_from" :to="filters.date_to" id-prefix="dashboard-date" @change="emit('change', $event)" />
    </section>
</template>
