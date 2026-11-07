<script setup lang="ts">
import PanelCard from '@/components/layout/PanelCard.vue';
import DateRangeFilter from '@/components/shared/DateRangeFilter.vue';
import FilterBar, { type ActiveFilter } from '@/components/shared/FilterBar.vue';
import SearchInput from '@/components/shared/SearchInput.vue';
import SelectFilter from '@/components/shared/SelectFilter.vue';
import TablePagination from '@/components/shared/TablePagination.vue';
import { useUrlFilters } from '@/composables/useUrlFilters';
import AuditLogTable from '@/features/audit/AuditLogTable.vue';
import type { AuditEntry, AuditFilters, Paginated } from '@/types/backoffice';
import { computed, ref, watch } from 'vue';

/**
 * Searchable, read-only audit list. Pengaturan → Aktivitas Sistem uses it with the scope filter; the old Audit Log page
 * (every entry) without it. Audit is append-only: nothing here edits an entry.
 */
const props = withDefaults(defineProps<{ filters: AuditFilters; logs: Paginated<AuditEntry>; path: string; withScope?: boolean }>(), { withScope: false });

const { apply, applyDebounced, loading, failed } = useUrlFilters(
    () => props.path,
    () => ({ ...props.filters }),
    ['filters', 'logs'],
);

const search = ref(props.filters.search ?? '');
watch(search, (value) => {
    if (value !== (props.filters.search ?? '')) void applyDebounced({ search: value || null, page: null });
});

const scopeOptions = [{ value: 'all', label: 'Semua audit' }];
const active = computed<ActiveFilter[]>(() =>
    [
        props.filters.search ? { key: 'search', label: `Cari: ${props.filters.search}` } : null,
        props.withScope && props.filters.scope === 'all' ? { key: 'scope', label: 'Lingkup: Semua audit' } : null,
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
    apply({ search: null, scope: null, date_from: null, date_to: null, page: null });
}
</script>

<template>
    <FilterBar :active="active" @remove="remove" @clear="clear">
        <div class="w-full md:w-72">
            <span aria-hidden="true" class="mb-1.5 block text-xs font-semibold text-muted-foreground">Cari</span>
            <SearchInput id="audit-search" v-model="search" label="Cari aksi, pelaku, atau target" placeholder="Cari aksi, pelaku, atau target" />
        </div>
        <!-- "Sistem" (the default) leaves out transactions and user management, which have their own pages. -->
        <SelectFilter
            v-if="withScope"
            id="filter-audit-scope"
            label="Lingkup"
            all-label="Sistem"
            :options="scopeOptions"
            :model-value="filters.scope === 'all' ? 'all' : null"
            @update:model-value="apply({ scope: $event, page: null })"
        />
        <DateRangeFilter :from="filters.date_from" :to="filters.date_to" id-prefix="audit-date" @change="apply({ ...$event, page: null })" />
    </FilterBar>
    <PanelCard flush>
        <AuditLogTable :rows="logs.data" :loading="loading" :failed="failed" @retry="apply({})" />
        <TablePagination :meta="logs.meta" :disabled="loading" @change="apply({ page: String($event) })" />
    </PanelCard>
</template>
