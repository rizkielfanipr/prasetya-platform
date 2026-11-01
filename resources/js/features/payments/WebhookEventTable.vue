<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import { rejectionLabels, webhookStatuses } from '@/lib/status';
import type { WebhookEventRow } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(defineProps<{ rows: WebhookEventRow[]; loading?: boolean; failed?: boolean; compact?: boolean }>(), {
    loading: false,
    failed: false,
    compact: false,
});
defineEmits<{ retry: [] }>();

const columns = computed<DataTableColumn[]>(() => [
    { key: 'id', label: 'Event' },
    ...(props.compact ? [] : [{ key: 'invoice', label: 'Invoice' }]),
    { key: 'type', label: 'Tipe' },
    { key: 'amount', label: 'Jumlah', align: 'right' as const },
    { key: 'signature', label: 'Tanda tangan' },
    { key: 'status', label: 'Pemrosesan' },
    { key: 'received', label: 'Diterima' },
    { key: 'processed', label: 'Diproses' },
    { key: 'requestId', label: 'Request ID' },
    { key: 'diagnostics', label: 'Diagnostik' },
]);
</script>

<template>
    <DataTable
        caption="Event pembayaran dari gateway"
        :columns="columns"
        :rows="rows"
        :row-key="(row) => row.id"
        :loading="loading"
        :failed="failed"
        @retry="$emit('retry')"
    >
        <template #cell-id="{ row }"
            ><code class="text-xs" :title="row.id">{{ row.id.slice(-10) }}</code></template
        >
        <template #cell-invoice="{ row }">
            <Link v-if="row.orderId" :href="routes.transaction(row.orderId)" class="whitespace-nowrap hover:underline">{{ row.invoice }}</Link>
            <span v-else class="whitespace-nowrap">{{ row.invoice ?? '—' }}</span>
        </template>
        <template #cell-type="{ row }"
            >{{ row.eventType ?? '—' }}<span class="block text-xs text-muted-foreground">{{ row.provider }}</span></template
        >
        <template #cell-amount="{ row }">{{ formatCurrency(row.amount) }}</template>
        <template #cell-signature="{ row }">
            <span class="font-semibold whitespace-nowrap" :class="row.signatureValid ? 'text-success' : 'text-danger'">{{
                row.signatureValid ? 'Valid' : 'Tidak valid'
            }}</span>
        </template>
        <template #cell-status="{ row }">
            <StatusBadge :status="webhookStatuses[row.status]" />
            <span v-if="row.rejectionReason" class="mt-1 block text-xs text-muted-foreground">{{
                rejectionLabels[row.rejectionReason] ?? row.rejectionReason
            }}</span>
        </template>
        <template #cell-received="{ row }"
            ><span class="whitespace-nowrap">{{ formatDateTime(row.receivedAt) }}</span></template
        >
        <template #cell-processed="{ row }"
            ><span class="whitespace-nowrap">{{ formatDateTime(row.processedAt) }}</span></template
        >
        <template #cell-requestId="{ row }"
            ><code class="text-xs" :title="row.requestId ?? ''">{{ row.requestId?.slice(-10) ?? '—' }}</code></template
        >
        <template #cell-diagnostics="{ row }">
            <details v-if="row.diagnostics">
                <summary class="text-xs font-semibold text-primary-text">Lihat</summary>
                <pre class="mt-1 max-w-72 overflow-x-auto rounded-sm bg-muted p-2 text-xs">{{ JSON.stringify(row.diagnostics, null, 2) }}</pre>
            </details>
            <span v-else class="text-xs text-muted-foreground">Tidak disimpan</span>
        </template>
        <template #empty><EmptyState title="Belum ada event" description="Notifikasi dari gateway akan tercatat di sini." /></template>
    </DataTable>
</template>
