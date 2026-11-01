<script setup lang="ts">
import BrandBadge from '@/components/shared/BrandBadge.vue';
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import OrderStatusBadge from '@/components/shared/OrderStatusBadge.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { usePermissions } from '@/composables/usePermissions';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import { rejectionLabels } from '@/lib/status';
import type { ReconciliationIssue } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import GatewayCheckDialog from './GatewayCheckDialog.vue';

defineProps<{ rows: ReconciliationIssue[]; loading?: boolean; failed?: boolean }>();
defineEmits<{ retry: [] }>();

const { can } = usePermissions();

const columns: DataTableColumn[] = [
    { key: 'invoice', label: 'Invoice' },
    { key: 'brand', label: 'Brand' },
    { key: 'amount', label: 'Jumlah', align: 'right' },
    { key: 'payment', label: 'Pembayaran' },
    { key: 'order', label: 'Pesanan' },
    { key: 'detail', label: 'Keterangan' },
    { key: 'at', label: 'Waktu' },
    { key: 'actions', label: 'Aksi', srOnlyLabel: true },
];
</script>

<template>
    <DataTable
        caption="Isu rekonsiliasi"
        :columns="columns"
        :rows="rows"
        :row-key="(row) => row.key"
        :loading="loading"
        :failed="failed"
        @retry="$emit('retry')"
    >
        <template #cell-invoice="{ row }">
            <Link v-if="row.orderId" :href="routes.transaction(row.orderId)" class="whitespace-nowrap hover:underline">{{ row.invoice }}</Link>
            <span v-else class="whitespace-nowrap">{{ row.invoice ?? 'Tidak diketahui' }}</span>
        </template>
        <template #cell-brand="{ row }"><BrandBadge v-if="row.brand" :brand="row.brand" /><span v-else>—</span></template>
        <template #cell-amount="{ row }">{{ formatCurrency(row.amount) }}</template>
        <template #cell-payment="{ row }"><PaymentStatusBadge v-if="row.orderId" :status="row.paymentStatus" /><span v-else>—</span></template>
        <template #cell-order="{ row }"><OrderStatusBadge v-if="row.orderStatus" :status="row.orderStatus" /><span v-else>—</span></template>
        <template #cell-detail="{ row }">{{ row.detail ? (rejectionLabels[row.detail] ?? row.detail) : '—' }}</template>
        <template #cell-at="{ row }"
            ><span class="whitespace-nowrap">{{ formatDateTime(row.at) }}</span></template
        >
        <template #cell-actions="{ row }">
            <GatewayCheckDialog
                v-if="row.checkablePaymentId && can('reconciliation.gateway-check')"
                :payment-id="row.checkablePaymentId"
                :invoice="row.invoice"
            />
        </template>
        <template #empty
            ><EmptyState :icon="CheckCircle2" title="Tidak ada isu di kelompok ini" description="Tidak ada catatan yang perlu ditinjau saat ini."
        /></template>
    </DataTable>
</template>
