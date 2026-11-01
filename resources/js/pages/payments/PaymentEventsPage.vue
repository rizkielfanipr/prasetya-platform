<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import DateRangeFilter from '@/components/shared/DateRangeFilter.vue';
import FilterBar, { type ActiveFilter } from '@/components/shared/FilterBar.vue';
import SearchInput from '@/components/shared/SearchInput.vue';
import SelectFilter from '@/components/shared/SelectFilter.vue';
import TablePagination from '@/components/shared/TablePagination.vue';
import { useUrlFilters } from '@/composables/useUrlFilters';
import WebhookEventTable from '@/features/payments/WebhookEventTable.vue';
import { routes } from '@/lib/routes';
import { webhookStatuses } from '@/lib/status';
import type { Paginated, WebhookEventRow, WebhookStatus } from '@/types/backoffice';
import { computed, ref, watch } from 'vue';

type Filters = { search: string | null; status: WebhookStatus | null; date_from: string | null; date_to: string | null };

const props = defineProps<{ filters: Filters; events: Paginated<WebhookEventRow> }>();

const { apply, applyDebounced, loading, failed } = useUrlFilters(
    () => routes.paymentEvents,
    () => ({ ...props.filters }),
    ['filters', 'events'],
);

const search = ref(props.filters.search ?? '');
watch(search, (value) => {
    if (value !== (props.filters.search ?? '')) void applyDebounced({ search: value || null, page: null });
});

const statusOptions = (Object.keys(webhookStatuses) as WebhookStatus[]).map((value) => ({ value, label: webhookStatuses[value].label }));
const active = computed<ActiveFilter[]>(() =>
    [
        props.filters.search ? { key: 'search', label: `Invoice: ${props.filters.search}` } : null,
        props.filters.status ? { key: 'status', label: `Status: ${webhookStatuses[props.filters.status].label}` } : null,
        props.filters.date_from ? { key: 'date_from', label: `Dari: ${props.filters.date_from}` } : null,
        props.filters.date_to ? { key: 'date_to', label: `Sampai: ${props.filters.date_to}` } : null,
    ].filter((filter): filter is ActiveFilter => filter !== null),
);

function clear(): void {
    search.value = '';
    apply({ search: null, status: null, date_from: null, date_to: null, page: null });
}

function remove(key: string): void {
    if (key === 'search') search.value = '';
    apply({ [key]: null, page: null });
}
</script>

<template>
    <AppLayout title="Event Pembayaran" description="Bukti notifikasi gateway yang sudah disaring">
        <FilterBar :active="active" @remove="remove" @clear="clear">
            <div class="w-full md:w-72">
                <span aria-hidden="true" class="mb-1.5 block text-xs font-semibold text-muted-foreground">Cari</span>
                <SearchInput id="event-search" v-model="search" label="Cari invoice" placeholder="Cari invoice" />
            </div>
            <SelectFilter
                id="filter-status"
                label="Pemrosesan"
                :options="statusOptions"
                :model-value="filters.status"
                @update:model-value="apply({ status: $event, page: null })"
            />
            <DateRangeFilter :from="filters.date_from" :to="filters.date_to" id-prefix="event-date" @change="apply({ ...$event, page: null })" />
        </FilterBar>
        <PanelCard flush>
            <WebhookEventTable :rows="events.data" :loading="loading" :failed="failed" @retry="apply({})" />
            <TablePagination :meta="events.meta" :disabled="loading" @change="apply({ page: String($event) })" />
        </PanelCard>
    </AppLayout>
</template>
