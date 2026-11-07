<script setup lang="ts">
import PanelCard from '@/components/layout/PanelCard.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import TablePagination from '@/components/shared/TablePagination.vue';
import { Button } from '@/components/ui/button';
import { useUrlFilters } from '@/composables/useUrlFilters';
import TransactionFilterBar from '@/features/transactions/TransactionFilterBar.vue';
import TransactionTable from '@/features/transactions/TransactionTable.vue';
import { copy } from '@/lib/copy';
import type { Paginated, TransactionFilterOptions, TransactionFilters, TransactionRow } from '@/types/backoffice';
import { computed } from 'vue';

/** Transaksi → Semua. Used by the workspace (path /transactions, baseQuery view=all) and the old list page. */
const props = withDefaults(
    defineProps<
        TransactionFilterOptions & { filters: TransactionFilters; transactions: Paginated<TransactionRow>; path: string; baseQuery?: Record<string, string> }
    >(),
    { baseQuery: () => ({}) },
);

const { apply, applyDebounced, loading, failed } = useUrlFilters(
    () => props.path,
    () => ({ ...props.baseQuery, ...props.filters }),
    ['filters', 'transactions', 'paymentMethods', 'packages', 'themeCodes', 'eventTypes'],
);

const hasFilters = computed(() => Object.entries(props.filters).some(([key, value]) => value && !['sort', 'direction', 'page'].includes(key)));

function toggleSort(key: string): void {
    const direction = props.filters.sort === key && props.filters.direction !== 'asc' ? 'asc' : 'desc';
    apply({ sort: key, direction, page: null });
}

const clearFilters = (): void =>
    apply({
        search: null,
        brand: null,
        payment_status: null,
        order_status: null,
        payment_method: null,
        package: null,
        theme_code: null,
        event_type: null,
        event_date: null,
        date_from: null,
        date_to: null,
        page: null,
    });
</script>

<template>
    <TransactionFilterBar
        :filters="filters"
        :options="{ paymentMethods, packages, themeCodes, eventTypes }"
        @change="apply"
        @search="applyDebounced({ search: $event, page: null })"
    />
    <PanelCard flush>
        <TransactionTable
            :rows="transactions.data"
            :loading="loading"
            :failed="failed"
            :sort="filters.sort ?? 'created_at'"
            :direction="filters.direction ?? 'desc'"
            @sort="toggleSort"
            @retry="apply({})"
        >
            <template #empty>
                <EmptyState v-if="hasFilters" title="Tidak ada transaksi yang cocok" description="Coba ubah atau hapus filter.">
                    <Button variant="outline" @click="clearFilters">{{ copy.resetFilters }}</Button>
                </EmptyState>
                <EmptyState v-else title="Belum ada transaksi" description="Pesanan dari Webworks dan Satamu akan muncul di sini." />
            </template>
        </TransactionTable>
        <TablePagination :meta="transactions.meta" :disabled="loading" @change="apply({ page: String($event) })" />
    </PanelCard>
</template>
