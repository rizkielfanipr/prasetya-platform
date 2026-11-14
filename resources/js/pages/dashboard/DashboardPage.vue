<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import SelectFilter from '@/components/shared/SelectFilter.vue';
import { useUrlFilters } from '@/composables/useUrlFilters';
import BrandSummaryCards from '@/features/dashboard/BrandSummaryCards.vue';
import DashboardMeta from '@/features/dashboard/DashboardMeta.vue';
import DashboardScope from '@/features/dashboard/DashboardScope.vue';
import PaymentRouteSummary from '@/features/dashboard/PaymentRouteSummary.vue';
import RevenuePanel from '@/features/dashboard/RevenuePanel.vue';
import TransactionTable from '@/features/transactions/TransactionTable.vue';
import { routes } from '@/lib/routes';
import { paymentStatuses } from '@/lib/status';
import type { BrandSummary, DashboardFilters, DashboardSummary, PaymentStatus, RevenuePoint, TransactionRow } from '@/types/backoffice';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Dashboard (DESIGN.md, "Rute sebagai Tulang Punggung"): the scope bar, the payment route at page scale, revenue
 * beside the two brands, then the latest transactions. Brand and period scope the route and the chart; the status
 * filter scopes only the latest transactions.
 */
const props = defineProps<{
    filters: DashboardFilters;
    summary: DashboardSummary;
    brands: BrandSummary[];
    revenue?: RevenuePoint[];
    recentTransactions: TransactionRow[];
}>();

const { apply, loading, failed } = useUrlFilters(
    () => routes.dashboard,
    () => ({ ...props.filters }),
    ['filters', 'summary', 'brands', 'revenue', 'recentTransactions'],
);

const comparison = computed(() => (props.filters.date_from || props.filters.date_to ? 'periode sebelumnya' : `${props.filters.period} hari sebelumnya`));
const statusOptions = (Object.keys(paymentStatuses) as PaymentStatus[]).map((value) => ({ value, label: paymentStatuses[value].label }));
</script>

<template>
    <AppLayout title="Dashboard" description="Ringkasan aktivitas bisnis">
        <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
            <DashboardScope :filters="filters" @change="apply" />
            <DashboardMeta class="hidden pb-2 lg:flex" compact :active-orders="summary.activeOrders" :brand="filters.brand" :comparison="comparison" />
        </div>

        <PaymentRouteSummary :summary="summary" :brand="filters.brand" :comparison="comparison" />

        <div class="grid gap-x-8 gap-y-6 xl:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
            <RevenuePanel :points="revenue" :brand="filters.brand" />
            <BrandSummaryCards :summaries="brands" />
        </div>

        <section aria-labelledby="recent-heading" class="space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h2 id="recent-heading" class="text-sm font-extrabold">Transaksi Terbaru</h2>
                <div class="flex flex-wrap items-end gap-3">
                    <SelectFilter
                        id="dashboard-status"
                        label="Status pembayaran"
                        :options="statusOptions"
                        :model-value="filters.payment_status"
                        @update:model-value="apply({ payment_status: $event })"
                    />
                    <Link
                        :href="routes.transactions"
                        class="inline-flex h-11 items-center gap-2 rounded-md border border-input bg-card px-4 text-sm font-bold transition-colors hover:bg-tint-100 motion-reduce:transition-none"
                        >Lihat Semua<ArrowRightIcon class="size-4 stroke-2" aria-hidden="true"
                    /></Link>
                </div>
            </div>
            <TransactionTable :rows="recentTransactions" :sortable="false" :loading="loading" :failed="failed" @retry="apply({})" />
        </section>
    </AppLayout>
</template>
