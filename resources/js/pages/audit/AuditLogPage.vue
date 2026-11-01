<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import DateRangeFilter from '@/components/shared/DateRangeFilter.vue';
import FilterBar, { type ActiveFilter } from '@/components/shared/FilterBar.vue';
import SearchInput from '@/components/shared/SearchInput.vue';
import TablePagination from '@/components/shared/TablePagination.vue';
import { useUrlFilters } from '@/composables/useUrlFilters';
import AuditLogTable from '@/features/audit/AuditLogTable.vue';
import { routes } from '@/lib/routes';
import type { AuditEntry, Paginated } from '@/types/backoffice';
import { computed, ref, watch } from 'vue';

type Filters = { search: string | null; date_from: string | null; date_to: string | null };

const props = defineProps<{ filters: Filters; logs: Paginated<AuditEntry> }>();

const { apply, applyDebounced, loading, failed } = useUrlFilters(
    () => routes.auditLog,
    () => ({ ...props.filters }),
    ['filters', 'logs'],
);

const search = ref(props.filters.search ?? '');
watch(search, (value) => {
    if (value !== (props.filters.search ?? '')) void applyDebounced({ search: value || null, page: null });
});

const active = computed<ActiveFilter[]>(() =>
    [
        props.filters.search ? { key: 'search', label: `Cari: ${props.filters.search}` } : null,
        props.filters.date_from ? { key: 'date_from', label: `Dari: ${props.filters.date_from}` } : null,
        props.filters.date_to ? { key: 'date_to', label: `Sampai: ${props.filters.date_to}` } : null,
    ].filter((filter): filter is ActiveFilter => filter !== null),
);

function remove(key: string): void {
    if (key === 'search') search.value = '';
    apply({ [key]: null, page: null });
}

function clear(): void {
    search.value = '';
    apply({ search: null, date_from: null, date_to: null, page: null });
}
</script>

<template>
    <AppLayout title="Audit Log" description="Catatan tindakan penting, tidak dapat diubah">
        <FilterBar :active="active" @remove="remove" @clear="clear">
            <div class="w-full md:w-72">
                <span aria-hidden="true" class="mb-1.5 block text-xs font-semibold text-muted-foreground">Cari</span>
                <SearchInput id="audit-search" v-model="search" label="Cari aksi, pelaku, atau target" placeholder="Cari aksi, pelaku, atau target" />
            </div>
            <DateRangeFilter :from="filters.date_from" :to="filters.date_to" id-prefix="audit-date" @change="apply({ ...$event, page: null })" />
        </FilterBar>
        <PanelCard flush>
            <AuditLogTable :rows="logs.data" :loading="loading" :failed="failed" @retry="apply({})" />
            <TablePagination :meta="logs.meta" :disabled="loading" @change="apply({ page: String($event) })" />
        </PanelCard>
    </AppLayout>
</template>
