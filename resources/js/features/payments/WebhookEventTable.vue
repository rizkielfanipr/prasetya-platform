<script setup lang="ts">
import DetailSheet from '@/components/shared/DetailSheet.vue';
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { copy } from '@/lib/copy';
import { formatCurrency, formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import { rejectionLabels, webhookStatuses } from '@/lib/status';
import type { WebhookEventRow } from '@/types/backoffice';
import { CheckCircleIcon, ShieldExclamationIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';

// The scan stays in the table; signatures, ids, and the raw diagnostics move to the sheet (DESIGN.md, Data Table).
withDefaults(defineProps<{ rows: WebhookEventRow[]; loading?: boolean; failed?: boolean }>(), {
    loading: false,
    failed: false,
});
defineEmits<{ retry: [] }>();

const columns: DataTableColumn[] = [
    { key: 'received', label: 'Diterima' },
    { key: 'invoice', label: 'Invoice' },
    { key: 'type', label: 'Tipe' },
    { key: 'amount', label: 'Jumlah', align: 'right' },
    { key: 'status', label: 'Pemrosesan' },
    { key: 'signature', label: 'Tanda tangan' },
    { key: 'detail', label: 'Detail', align: 'right', srOnlyLabel: true },
];

const selected = ref<WebhookEventRow | null>(null);
const open = ref(false);

function inspect(row: WebhookEventRow): void {
    selected.value = row;
    open.value = true;
}

function diagnosticsJson(row: WebhookEventRow): string {
    return JSON.stringify(row.diagnostics, null, 2);
}
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
        <template #cell-received="{ row }"
            ><span class="text-[13px] whitespace-nowrap">{{ formatDateTime(row.receivedAt) }}</span></template
        >
        <template #cell-invoice="{ row }">
            <Link
                v-if="row.orderId"
                :href="routes.transaction(row.orderId)"
                class="font-bold whitespace-nowrap text-primary-text underline decoration-tint-300 underline-offset-4 hover:decoration-tint-600"
                >{{ row.invoice }}</Link
            >
            <!-- No order matches this evidence: say so instead of rendering a link to nowhere. -->
            <span v-else class="whitespace-nowrap"
                >{{ row.invoice ?? '—'
                }}<span class="block text-[12px] text-muted-foreground" data-testid="no-transaction">{{ copy.noTransaction }}</span></span
            >
        </template>
        <template #cell-type="{ row }"
            >{{ row.eventType ?? '—' }}<span class="block text-[12px] text-muted-foreground">{{ row.provider }}</span></template
        >
        <template #cell-amount="{ row }"
            ><span class="whitespace-nowrap tabular-nums">{{ formatCurrency(row.amount) }}</span></template
        >
        <template #cell-status="{ row }">
            <StatusBadge :status="webhookStatuses[row.status]" />
            <span v-if="row.rejectionReason" class="mt-1 block text-[12px] text-muted-foreground">{{
                rejectionLabels[row.rejectionReason] ?? row.rejectionReason
            }}</span>
        </template>
        <template #cell-signature="{ row }">
            <span
                class="inline-flex items-center gap-1.5 text-[13px] font-semibold whitespace-nowrap"
                :class="row.signatureValid ? 'text-sign-ink' : 'text-danger'"
            >
                <component :is="row.signatureValid ? CheckCircleIcon : ShieldExclamationIcon" class="size-3.5 shrink-0 stroke-2" aria-hidden="true" />
                {{ row.signatureValid ? 'Valid' : 'Tidak valid' }}
            </span>
        </template>
        <template #cell-detail="{ row }">
            <button
                type="button"
                class="focus-visible:outline-line-orange text-[13px] font-bold whitespace-nowrap text-primary-text underline decoration-tint-300 underline-offset-4 hover:decoration-tint-600 focus-visible:outline-2 focus-visible:outline-offset-2"
                :aria-label="`Lihat detail event ${row.invoice ?? row.id}`"
                @click="inspect(row)"
            >
                Lihat
            </button>
        </template>
        <template #empty><EmptyState title="Belum ada event" description="Notifikasi dari gateway akan tercatat di sini." /></template>
    </DataTable>

    <DetailSheet v-model:open="open" :title="selected?.invoice ? `Event ${selected.invoice}` : 'Event gateway'" description="Catatan teknis dari gateway.">
        <dl v-if="selected" class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Event</dt>
                <dd class="text-sign-ink mt-1 font-mono text-[12px] break-all">{{ selected.id }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Jenis</dt>
                <dd class="text-sign-ink mt-1 text-[14px]">
                    {{ selected.eventType ?? '—' }}
                    <span class="block text-[12px] text-muted-foreground"
                        >{{ selected.provider }} <template v-if="selected.channel">· {{ selected.channel }}</template></span
                    >
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Jumlah</dt>
                <dd class="text-sign-ink mt-1 text-[14px] tabular-nums">{{ formatCurrency(selected.amount) }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Pemrosesan</dt>
                <dd class="mt-1.5"><StatusBadge :status="webhookStatuses[selected.status]" /></dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Diterima</dt>
                <dd class="text-sign-ink mt-1 text-[14px]">{{ formatDateTime(selected.receivedAt) }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Diproses</dt>
                <dd class="text-sign-ink mt-1 text-[14px]">{{ formatDateTime(selected.processedAt) }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Tanda tangan</dt>
                <dd class="text-sign-ink mt-1 text-[14px]">{{ selected.signatureValid ? 'Valid' : 'Tidak valid' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Duplikat</dt>
                <dd class="text-sign-ink mt-1 text-[14px]">{{ selected.isDuplicate ? 'Ya, sudah pernah diterima' : 'Tidak' }}</dd>
            </div>
            <div v-if="selected.rejectionReason" class="sm:col-span-2">
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Alasan ditolak</dt>
                <dd class="text-sign-ink mt-1 text-[14px]">{{ rejectionLabels[selected.rejectionReason] ?? selected.rejectionReason }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Request ID</dt>
                <dd class="text-sign-ink mt-1 font-mono text-[13px] break-all">{{ selected.requestId ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Diagnostik</dt>
                <dd class="mt-2">
                    <pre v-if="selected.diagnostics" class="text-sign-ink overflow-x-auto rounded-lg bg-tint-50 p-3 font-mono text-[12px]">{{
                        diagnosticsJson(selected)
                    }}</pre>
                    <span v-else class="text-[13px] text-muted-foreground">Tidak disimpan</span>
                </dd>
            </div>
        </dl>
    </DetailSheet>
</template>
