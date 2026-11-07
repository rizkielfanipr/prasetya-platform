<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import FilterBar, { type ActiveFilter } from '@/components/shared/FilterBar.vue';
import SearchInput from '@/components/shared/SearchInput.vue';
import SelectFilter from '@/components/shared/SelectFilter.vue';
import TablePagination from '@/components/shared/TablePagination.vue';
import { useUrlFilters } from '@/composables/useUrlFilters';
import UserCreateDialog from '@/features/users/UserCreateDialog.vue';
import UserTable from '@/features/users/UserTable.vue';
import { routes } from '@/lib/routes';
import { userStatuses } from '@/lib/status';
import type { Paginated, UserFilters, UserRow } from '@/types/backoffice';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ filters: UserFilters; users: Paginated<UserRow> }>();

const { apply, applyDebounced, loading, failed } = useUrlFilters(
    () => routes.users,
    () => ({ ...props.filters }),
    ['filters', 'users'],
);

const search = ref(props.filters.search ?? '');
watch(search, (value) => {
    if (value !== (props.filters.search ?? '')) void applyDebounced({ search: value || null, page: null });
});

const statusOptions = (['active', 'inactive'] as const).map((value) => ({ value, label: userStatuses[value].label }));

const active = computed<ActiveFilter[]>(() =>
    [
        props.filters.search ? { key: 'search', label: `Cari: ${props.filters.search}` } : null,
        props.filters.status ? { key: 'status', label: `Status: ${userStatuses[props.filters.status].label}` } : null,
    ].filter((filter): filter is ActiveFilter => filter !== null),
);

function remove(key: string): void {
    if (key === 'search') search.value = '';
    apply({ [key]: null, page: null });
}

function clear(): void {
    search.value = '';
    apply({ search: null, status: null, page: null });
}
</script>

<template>
    <AppLayout title="Pengguna" description="Semua pengguna aktif dapat membuka semua menu">
        <FilterBar :active="active" @remove="remove" @clear="clear">
            <div class="w-full md:w-72">
                <span aria-hidden="true" class="mb-1.5 block text-xs font-semibold text-muted-foreground">Cari</span>
                <SearchInput id="user-search" v-model="search" label="Cari nama atau username" placeholder="Cari nama atau username" />
            </div>
            <SelectFilter
                id="filter-user-status"
                label="Status"
                :options="statusOptions"
                :model-value="filters.status"
                @update:model-value="apply({ status: $event, page: null })"
            />
            <div class="md:ml-auto"><UserCreateDialog /></div>
        </FilterBar>
        <PanelCard flush>
            <UserTable :rows="users.data" :loading="loading" :failed="failed" :filtered="active.length > 0" @retry="apply({})" />
            <TablePagination :meta="users.meta" :disabled="loading" @change="apply({ page: String($event) })" />
        </PanelCard>
    </AppLayout>
</template>
