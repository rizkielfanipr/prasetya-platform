<script setup lang="ts">
import BrandBadge from '@/components/shared/BrandBadge.vue';
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import PaymentRoute from '@/components/shared/PaymentRoute.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { formatCurrency, formatDate, formatDateTime, formatInvoice, formatTime } from '@/lib/format';
import { paymentMethodLabel } from '@/lib/payment-methods';
import { orderStatuses } from '@/lib/status';
import { routes } from '@/lib/routes';
import type { TransactionRow } from '@/types/backoffice';
import { Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        rows: TransactionRow[];
        variant?: 'compact' | 'full';
        loading?: boolean;
        failed?: boolean;
        sort?: string | null;
        direction?: 'asc' | 'desc' | null;
    }>(),
    { variant: 'full', loading: false, failed: false, sort: null, direction: null },
);

defineEmits<{ sort: [key: string]; retry: [] }>();

// The invoice is the row's only link and its anchor (DESIGN.md); hover and keyboard focus both load the detail ahead.
const PREFETCH = { cacheFor: '30s' } as const;
const prefetchDetail = (id: string): void => {
    router.prefetch(routes.transaction(id), { method: 'get' }, PREFETCH);
};

const columns = computed<DataTableColumn[]>(() =>
    props.variant === 'compact'
        ? [
              { key: 'invoice', label: 'Invoice' },
              { key: 'brand', label: 'Brand' },
              { key: 'customer', label: 'Pelanggan' },
              { key: 'package', label: 'Paket' },
              { key: 'amount', label: 'Jumlah', align: 'right' },
              { key: 'paymentStatus', label: 'Status' },
              { key: 'created', label: 'Tanggal' },
          ]
        : [
              // The brand rides under the invoice: one column less, and the name stays visible at laptop widths.
              { key: 'invoice_number', label: 'Invoice', sortable: true },
              { key: 'customer', label: 'Pelanggan' },
              { key: 'total_amount', label: 'Jumlah', align: 'right', sortable: true },
              { key: 'route', label: 'Pembayaran' },
              { key: 'orderStatus', label: 'Pesanan' },
              { key: 'created_at', label: 'Dibuat', sortable: true },
          ],
);
</script>

<template>
    <DataTable
        caption="Daftar transaksi"
        :columns="columns"
        :rows="rows"
        :row-key="(row) => row.id"
        :loading="loading"
        :failed="failed"
        :sort="sort"
        :direction="direction"
        :stack-on-mobile="variant === 'full'"
        :class="{ 'transaction-table': variant === 'full' }"
        @sort="$emit('sort', $event)"
        @retry="$emit('retry')"
    >
        <template #cell-invoice="{ row }">
            <Link
                :href="routes.transaction(row.id)"
                prefetch
                :cache-for="PREFETCH.cacheFor"
                class="font-mono font-semibold whitespace-nowrap hover:underline"
                >{{ formatInvoice(row.invoice) }}</Link
            >
        </template>
        <template #cell-invoice_number="{ row }">
            <Link
                :href="routes.transaction(row.id)"
                prefetch
                :cache-for="PREFETCH.cacheFor"
                class="invoice-anchor font-mono text-sm font-semibold whitespace-nowrap text-foreground underline decoration-tint-300 decoration-2 underline-offset-4 transition-colors hover:decoration-tint-600 focus-visible:decoration-tint-600 motion-reduce:transition-none"
                @focus="prefetchDetail(row.id)"
                >{{ formatInvoice(row.invoice) }}</Link
            >
            <span class="mt-1.5 block"><BrandBadge :brand="row.brand" /></span>
        </template>
        <template #cell-brand="{ row }"><BrandBadge :brand="row.brand" /></template>
        <template #cell-customer="{ row }">
            <span class="block max-w-60 truncate font-semibold text-foreground">{{ row.customerName }}</span>
            <span v-if="variant === 'full'" class="block max-w-60 truncate text-xs text-muted-foreground">
                {{ row.packageName ?? 'Tanpa paket'
                }}<template v-if="row.paymentMethod"
                    ><span class="mx-1.5" aria-hidden="true">·</span><span class="sr-only">, </span>{{ paymentMethodLabel(row.paymentMethod) }}</template
                >
            </span>
        </template>
        <template #cell-package="{ row }"
            ><span class="whitespace-nowrap">{{ row.packageName ?? '—' }}</span></template
        >
        <template #cell-amount="{ row }">{{ formatCurrency(row.amount) }}</template>
        <template #cell-total_amount="{ row }"
            ><span class="text-sm font-semibold whitespace-nowrap">{{ formatCurrency(row.amount) }}</span></template
        >
        <template #cell-paymentStatus="{ row }"><PaymentStatusBadge :status="row.paymentStatus" /></template>
        <template #cell-route="{ row }">
            <PaymentRoute :status="row.paymentStatus" />
            <span v-if="row.lastPaymentUpdate" class="mt-0.5 block text-xs whitespace-nowrap text-muted-foreground tabular">
                Update {{ formatDateTime(row.lastPaymentUpdate) }}
            </span>
        </template>
        <template #cell-orderStatus="{ row }">
            <!-- Only the payment route carries status colour; the order status reads in ink beside it, red when it failed. -->
            <span
                class="text-[13px] font-semibold whitespace-nowrap"
                :class="orderStatuses[row.orderStatus].tone === 'danger' ? 'text-danger' : 'text-sidebar-foreground'"
                >{{ orderStatuses[row.orderStatus].label }}</span
            >
        </template>
        <template #cell-created="{ row }"
            ><span class="whitespace-nowrap">{{ formatDate(row.createdAt) }}</span></template
        >
        <template #cell-created_at="{ row }">
            <span class="block font-semibold whitespace-nowrap tabular">{{ formatDate(row.createdAt) }}</span>
            <span class="block text-xs whitespace-nowrap text-muted-foreground tabular">{{ formatTime(row.createdAt) }}</span>
        </template>
        <template #empty>
            <slot name="empty"><EmptyState title="Tidak ada transaksi" description="Belum ada transaksi yang cocok." /></slot>
        </template>
    </DataTable>
</template>

<style>
/*
 * When the table has less than 60rem, each transaction becomes a block laid out from DataTable's data-col cells.
 * Medium: invoice (with brand) and customer | payment route and order | amount and date.
 * Narrow (< 34rem): invoice and amount, customer and date, route and order.
 */
@container (width < 60rem) {
    .transaction-table tbody tr {
        grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) auto;
        grid-template-areas:
            'invoice route amount'
            'customer order created';
    }

    .transaction-table td[data-col='invoice_number'] {
        grid-area: invoice;
    }

    .transaction-table td[data-col='total_amount'] {
        grid-area: amount;
    }

    .transaction-table td[data-col='customer'] {
        grid-area: customer;
    }

    .transaction-table td[data-col='route'] {
        grid-area: route;
    }

    .transaction-table td[data-col='orderStatus'] {
        grid-area: order;
    }

    .transaction-table td[data-col='created_at'] {
        grid-area: created;
        text-align: right;
    }
}

@container (width < 34rem) {
    .transaction-table tbody tr {
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
            'invoice amount'
            'customer created'
            'route order';
    }

    .transaction-table td[data-col='orderStatus'] {
        align-self: start;
        text-align: right;
    }
}
</style>
