<script setup lang="ts">
import BrandBadge from '@/components/shared/BrandBadge.vue';
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import PaymentRoute from '@/components/shared/PaymentRoute.vue';
import { copy } from '@/lib/copy';
import { formatCurrency, formatDate, formatInvoice, formatTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import { orderStatuses, rejectionLabels } from '@/lib/status';
import type { ReconciliationIssue } from '@/types/backoffice';
import { CheckCircleIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import GatewayCheckDialog from './GatewayCheckDialog.vue';

withDefaults(defineProps<{ rows: ReconciliationIssue[]; loading?: boolean; failed?: boolean; caption?: string }>(), { caption: 'Isu yang perlu ditinjau' });
defineEmits<{ retry: [] }>();

// Same anatomy as the transaction table (DESIGN.md): the invoice is the anchor, the brand rides under it, the route
// carries the payment status. An issue without a transaction says so where the route would be.
const columns: DataTableColumn[] = [
    { key: 'invoice', label: 'Invoice' },
    { key: 'route', label: 'Status' },
    { key: 'amount', label: 'Jumlah', align: 'right' },
    { key: 'detail', label: 'Keterangan' },
    { key: 'actions', label: 'Aksi', srOnlyLabel: true },
];
</script>

<template>
    <DataTable
        class="issue-table"
        :caption="caption"
        :columns="columns"
        :rows="rows"
        :row-key="(row) => row.key"
        :loading="loading"
        :failed="failed"
        stack-on-mobile
        @retry="$emit('retry')"
    >
        <template #cell-invoice="{ row }">
            <Link
                v-if="row.orderId"
                :href="routes.transaction(row.orderId)"
                prefetch="hover"
                class="font-mono text-sm font-semibold whitespace-nowrap text-foreground underline decoration-tint-300 decoration-2 underline-offset-4 transition-colors hover:decoration-tint-600 focus-visible:decoration-tint-600 motion-reduce:transition-none"
                >{{ formatInvoice(row.invoice) }}</Link
            >
            <span v-else-if="row.invoice" class="font-mono text-sm font-semibold whitespace-nowrap">{{ row.invoice }}</span>
            <span v-else class="text-sm font-semibold text-muted-foreground">Invoice tidak diketahui</span>
            <span v-if="row.brand" class="mt-1.5 block"><BrandBadge :brand="row.brand" /></span>
        </template>
        <template #cell-route="{ row }">
            <template v-if="row.orderId">
                <PaymentRoute :status="row.paymentStatus" />
                <span v-if="row.orderStatus" class="mt-1 block text-xs font-semibold text-sidebar-foreground"
                    >Pesanan {{ orderStatuses[row.orderStatus].label }}</span
                >
            </template>
            <span v-else class="text-[13px] font-semibold text-muted-foreground" data-testid="no-transaction">{{ copy.noTransaction }}</span>
        </template>
        <template #cell-amount="{ row }"
            ><span class="text-sm font-semibold whitespace-nowrap tabular">{{ formatCurrency(row.amount) }}</span></template
        >
        <template #cell-detail="{ row }">
            <span v-if="row.detail" class="block text-[13px] font-semibold">{{ rejectionLabels[row.detail] ?? row.detail }}</span>
            <span class="block text-xs whitespace-nowrap text-muted-foreground tabular"
                ><time :datetime="row.at">{{ formatDate(row.at) }}, {{ formatTime(row.at) }}</time></span
            >
        </template>
        <template #cell-actions="{ row }">
            <GatewayCheckDialog v-if="row.checkablePaymentId" :payment-id="row.checkablePaymentId" :invoice="row.invoice" />
        </template>
        <template #empty
            ><EmptyState :icon="CheckCircleIcon" title="Antrean ini kosong" description="Tidak ada yang perlu ditinjau di sini saat ini."
        /></template>
    </DataTable>
</template>

<style>
@container (width < 60rem) {
    .issue-table tbody tr {
        grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) auto;
        grid-template-areas:
            'invoice route amount'
            'detail detail actions';
    }

    .issue-table td[data-col='invoice'] {
        grid-area: invoice;
    }

    .issue-table td[data-col='route'] {
        grid-area: route;
    }

    .issue-table td[data-col='amount'] {
        grid-area: amount;
        text-align: right;
    }

    .issue-table td[data-col='detail'] {
        grid-area: detail;
    }

    .issue-table td[data-col='actions'] {
        grid-area: actions;
        justify-self: end;
    }
}

@container (width < 34rem) {
    .issue-table tbody tr {
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas:
            'invoice amount'
            'route route'
            'detail actions';
    }
}
</style>
