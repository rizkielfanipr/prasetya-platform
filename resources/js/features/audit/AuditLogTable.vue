<script setup lang="ts">
import DetailSheet from '@/components/shared/DetailSheet.vue';
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import { formatDateTime } from '@/lib/format';
import { auditActionLabels } from '@/lib/status';
import type { AuditEntry, JsonState } from '@/types/backoffice';
import { ref } from 'vue';

// Five columns carry the scan; the before/after states and the request id move to a sheet so the
// table never needs a horizontal scroll (DESIGN.md, Data Table).
defineProps<{ rows: AuditEntry[]; loading?: boolean; failed?: boolean }>();
defineEmits<{ retry: [] }>();

const columns: DataTableColumn[] = [
    { key: 'at', label: 'Waktu' },
    { key: 'actor', label: 'Pelaku' },
    { key: 'action', label: 'Aksi' },
    { key: 'entity', label: 'Target' },
    { key: 'reason', label: 'Alasan' },
    { key: 'detail', label: 'Detail', align: 'right', srOnlyLabel: true },
];

const selected = ref<AuditEntry | null>(null);
const open = ref(false);

function inspect(row: AuditEntry): void {
    selected.value = row;
    open.value = true;
}

function describe(state: JsonState | null): string {
    return state
        ? Object.entries(state)
              .map(([key, value]) => `${key}: ${String(value)}`)
              .join(', ')
        : '—';
}

const selectedAction = (): string => (selected.value ? (auditActionLabels[selected.value.action] ?? selected.value.action) : '');
</script>

<template>
    <DataTable caption="Audit log" :columns="columns" :rows="rows" :row-key="(row) => row.id" :loading="loading" :failed="failed" @retry="$emit('retry')">
        <template #cell-at="{ row }"
            ><span class="text-[13px] whitespace-nowrap">{{ formatDateTime(row.at) }}</span></template
        >
        <template #cell-actor="{ row }"
            ><span class="whitespace-nowrap">{{ row.actor }}</span></template
        >
        <template #cell-action="{ row }">
            <span class="whitespace-nowrap">{{ auditActionLabels[row.action] ?? row.action }}</span>
        </template>
        <template #cell-entity="{ row }"
            >{{ row.entityType }}<code class="block font-mono text-[12px] text-muted-foreground">{{ row.entityId ?? '—' }}</code></template
        >
        <template #cell-reason="{ row }">
            <span class="text-sign-ink block max-w-64 text-[13px] break-words">{{ row.reason ?? '—' }}</span>
        </template>
        <template #cell-detail="{ row }">
            <button
                type="button"
                class="focus-visible:outline-line-orange text-[13px] font-bold whitespace-nowrap text-primary-text underline decoration-tint-300 underline-offset-4 hover:decoration-tint-600 focus-visible:outline-2 focus-visible:outline-offset-2"
                :aria-label="`Lihat detail ${auditActionLabels[row.action] ?? row.action}`"
                @click="inspect(row)"
            >
                Lihat
            </button>
        </template>
        <template #empty><EmptyState title="Belum ada catatan audit" /></template>
    </DataTable>

    <DetailSheet v-model:open="open" :title="selectedAction()" description="Catatan ini tidak dapat diubah.">
        <dl v-if="selected" class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Waktu</dt>
                <dd class="text-sign-ink mt-1 text-[14px]">{{ formatDateTime(selected.at) }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Pelaku</dt>
                <dd class="text-sign-ink mt-1 text-[14px]">{{ selected.actor }}</dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Aksi</dt>
                <dd class="text-sign-ink mt-1 text-[14px]">
                    {{ auditActionLabels[selected.action] ?? selected.action }}
                    <code class="block font-mono text-[12px] text-muted-foreground">{{ selected.action }}</code>
                </dd>
            </div>
            <div>
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Target</dt>
                <dd class="text-sign-ink mt-1 text-[14px]">
                    {{ selected.entityType }}
                    <code class="block font-mono text-[12px] break-all text-muted-foreground">{{ selected.entityId ?? '—' }}</code>
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Alasan</dt>
                <dd class="text-sign-ink mt-1 text-[14px] break-words">{{ selected.reason ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Perubahan</dt>
                <dd class="mt-2 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-lg bg-tint-50 p-3">
                        <p class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Sebelumnya</p>
                        <p class="text-sign-ink mt-1.5 font-mono text-[12px] break-words">{{ describe(selected.previousState) }}</p>
                    </div>
                    <div class="rounded-lg bg-tint-50 p-3">
                        <p class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Sesudahnya</p>
                        <p class="text-sign-ink mt-1.5 font-mono text-[12px] break-words">{{ describe(selected.newState) }}</p>
                    </div>
                </dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Request ID</dt>
                <dd class="text-sign-ink mt-1 font-mono text-[13px] break-all">{{ selected.requestId ?? '—' }}</dd>
            </div>
        </dl>
    </DetailSheet>
</template>
