<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import DateRangeFilter from '@/components/shared/DateRangeFilter.vue';
import MetricCard from '@/components/shared/MetricCard.vue';
import SelectFilter from '@/components/shared/SelectFilter.vue';
import { Button } from '@/components/ui/button';
import { useUrlFilters } from '@/composables/useUrlFilters';
import BrandSummaryCards from '@/features/dashboard/BrandSummaryCards.vue';
import RevenuePanel from '@/features/dashboard/RevenuePanel.vue';
import TransactionTable from '@/features/transactions/TransactionTable.vue';
import { formatCurrency, formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import { paymentStatuses } from '@/lib/status';
import type { BrandSummary, DashboardFilters, DashboardSummary, PaymentStatus, RevenuePoint, TransactionRow } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { ArrowRight, BarChart3, CheckCircle2, Clock, Package, XCircle } from '@lucide/vue';
import { computed } from 'vue';

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

const comparison = computed(() =>
    props.filters.date_from || props.filters.date_to ? 'dari periode sebelumnya' : `dari ${props.filters.period} hari sebelumnya`,
);
const statusOptions = (Object.keys(paymentStatuses) as PaymentStatus[]).map((value) => ({ value, label: paymentStatuses[value].label }));
</script>

<template>
    <AppLayout title="Dashboard" description="Ringkasan aktivitas bisnis">
        <section aria-label="Ringkasan" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
            <MetricCard
                class="2xl:col-span-2"
                label="Total Pendapatan"
                :value="formatCurrency(summary.totalPaid.value)"
                :icon="BarChart3"
                tone="primary"
                :current="summary.totalPaid.value"
                :previous="summary.totalPaid.previous"
                :comparison-label="comparison"
            />
            <MetricCard
                label="Transaksi Berhasil"
                :value="formatNumber(summary.successful.value)"
                :icon="CheckCircle2"
                tone="success"
                :current="summary.successful.value"
                :previous="summary.successful.previous"
                :comparison-label="comparison"
            />
            <MetricCard
                label="Menunggu Pembayaran"
                :value="formatNumber(summary.pending.value)"
                :icon="Clock"
                tone="warning"
                :current="summary.pending.value"
                :previous="summary.pending.previous"
                :comparison-label="comparison"
            />
            <MetricCard
                label="Gagal / Kedaluwarsa"
                :value="formatNumber(summary.failedOrExpired.value)"
                :icon="XCircle"
                tone="danger"
                :current="summary.failedOrExpired.value"
                :previous="summary.failedOrExpired.previous"
                :comparison-label="comparison"
            />
            <MetricCard
                label="Pesanan Aktif"
                :value="formatNumber(summary.activeOrders.value)"
                :icon="Package"
                tone="primary"
                :current="summary.activeOrders.value"
                :previous="summary.activeOrders.previous"
                :comparison-label="comparison"
            />
        </section>

        <RevenuePanel
            :points="revenue"
            :brand="filters.brand"
            :period="filters.period"
            :custom-range="Boolean(filters.date_from || filters.date_to)"
            @change="apply"
        />

        <BrandSummaryCards :summaries="brands" />

        <PanelCard title="Transaksi Terbaru" flush>
            <template #actions>
                <div class="flex flex-wrap items-end gap-3">
                    <SelectFilter
                        id="dashboard-status"
                        label="Status pembayaran"
                        :options="statusOptions"
                        :model-value="filters.payment_status"
                        @update:model-value="apply({ payment_status: $event })"
                    />
                    <DateRangeFilter :from="filters.date_from" :to="filters.date_to" id-prefix="dashboard-date" @change="apply($event)" />
                    <Button as-child variant="outline" class="h-11">
                        <Link :href="routes.transactions">Lihat semua<ArrowRight aria-hidden="true" /></Link>
                    </Button>
                </div>
            </template>
            <TransactionTable :rows="recentTransactions" variant="compact" :loading="loading" :failed="failed" @retry="apply({})" />
        </PanelCard>
    </AppLayout>
</template>
