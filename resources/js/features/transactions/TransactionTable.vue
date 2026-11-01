<script setup lang="ts">
import BrandBadge from '@/components/shared/BrandBadge.vue';
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import OrderStatusBadge from '@/components/shared/OrderStatusBadge.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { formatCurrency, formatDate, formatDateTime, formatInvoice } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { TransactionRow } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { Eye, MoreHorizontal } from '@lucide/vue';
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
              { key: 'actions', label: 'Aksi', srOnlyLabel: true },
          ]
        : [
              { key: 'invoice_number', label: 'Invoice', sortable: true },
              { key: 'brand', label: 'Brand' },
              { key: 'customer', label: 'Pelanggan' },
              { key: 'package', label: 'Paket' },
              { key: 'total_amount', label: 'Jumlah', align: 'right', sortable: true },
              { key: 'paymentStatus', label: 'Pembayaran' },
              { key: 'orderStatus', label: 'Pesanan' },
              { key: 'method', label: 'Metode' },
              { key: 'created_at', label: 'Dibuat', sortable: true },
              { key: 'lastUpdate', label: 'Update bayar' },
              { key: 'actions', label: 'Aksi', srOnlyLabel: true },
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
        @sort="$emit('sort', $event)"
        @retry="$emit('retry')"
    >
        <template #cell-invoice="{ row }">
            <Link :href="routes.transaction(row.id)" class="whitespace-nowrap hover:underline">{{ formatInvoice(row.invoice) }}</Link>
        </template>
        <template #cell-invoice_number="{ row }">
            <Link :href="routes.transaction(row.id)" class="whitespace-nowrap hover:underline">{{ formatInvoice(row.invoice) }}</Link>
        </template>
        <template #cell-brand="{ row }"><BrandBadge :brand="row.brand" /></template>
        <template #cell-customer="{ row }"
            ><span class="block max-w-44 truncate">{{ row.customerName }}</span></template
        >
        <template #cell-package="{ row }"
            ><span class="whitespace-nowrap">{{ row.packageName ?? '—' }}</span></template
        >
        <template #cell-amount="{ row }">{{ formatCurrency(row.amount) }}</template>
        <template #cell-total_amount="{ row }">{{ formatCurrency(row.amount) }}</template>
        <template #cell-paymentStatus="{ row }"><PaymentStatusBadge :status="row.paymentStatus" /></template>
        <template #cell-orderStatus="{ row }"><OrderStatusBadge :status="row.orderStatus" /></template>
        <template #cell-method="{ row }"
            ><span class="whitespace-nowrap">{{ row.paymentMethod ?? '—' }}</span></template
        >
        <template #cell-created="{ row }"
            ><span class="whitespace-nowrap">{{ formatDate(row.createdAt) }}</span></template
        >
        <template #cell-created_at="{ row }"
            ><span class="whitespace-nowrap">{{ formatDate(row.createdAt) }}</span></template
        >
        <template #cell-lastUpdate="{ row }"
            ><span class="whitespace-nowrap">{{ formatDateTime(row.lastPaymentUpdate) }}</span></template
        >
        <template #cell-actions="{ row }">
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" size="icon" class="size-9" :aria-label="`Aksi untuk ${row.invoice}`"><MoreHorizontal aria-hidden="true" /></Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem as-child>
                        <Link :href="routes.transaction(row.id)" class="w-full"><Eye aria-hidden="true" />Lihat detail</Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </template>
        <template #empty>
            <slot name="empty"><EmptyState title="Tidak ada transaksi" description="Belum ada transaksi yang cocok." /></slot>
        </template>
    </DataTable>
</template>
