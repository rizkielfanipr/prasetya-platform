<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import { formatDateTime } from '@/lib/format';
import { auditActionLabels } from '@/lib/status';
import type { AuditEntry, JsonState } from '@/types/backoffice';

defineProps<{ rows: AuditEntry[]; loading?: boolean; failed?: boolean }>();
defineEmits<{ retry: [] }>();

const columns: DataTableColumn[] = [
    { key: 'at', label: 'Waktu' },
    { key: 'actor', label: 'Pelaku' },
    { key: 'action', label: 'Aksi' },
    { key: 'entity', label: 'Target' },
    { key: 'previous', label: 'Sebelumnya' },
    { key: 'next', label: 'Sesudahnya' },
    { key: 'reason', label: 'Alasan' },
    { key: 'requestId', label: 'Request ID' },
];

const describe = (state: JsonState | null): string =>
    state
        ? Object.entries(state)
              .map(([key, value]) => `${key}: ${String(value)}`)
              .join(', ')
        : '—';
</script>

<template>
    <DataTable caption="Audit log" :columns="columns" :rows="rows" :row-key="(row) => row.id" :loading="loading" :failed="failed" @retry="$emit('retry')">
        <template #cell-at="{ row }"
            ><span class="whitespace-nowrap">{{ formatDateTime(row.at) }}</span></template
        >
        <template #cell-actor="{ row }"
            ><span class="whitespace-nowrap">{{ row.actor }}</span></template
        >
        <template #cell-action="{ row }">
            <span class="whitespace-nowrap">{{ auditActionLabels[row.action] ?? row.action }}</span>
            <code class="block text-xs whitespace-nowrap text-muted-foreground">{{ row.action }}</code>
        </template>
        <template #cell-entity="{ row }"
            >{{ row.entityType
            }}<code class="block text-xs text-muted-foreground" :title="row.entityId ?? ''">{{ row.entityId?.slice(-10) ?? '—' }}</code></template
        >
        <template #cell-previous="{ row }"
            ><span class="block max-w-52 text-xs break-words">{{ describe(row.previousState) }}</span></template
        >
        <template #cell-next="{ row }"
            ><span class="block max-w-52 text-xs break-words">{{ describe(row.newState) }}</span></template
        >
        <template #cell-reason="{ row }">{{ row.reason ?? '—' }}</template>
        <template #cell-requestId="{ row }"
            ><code class="text-xs" :title="row.requestId ?? ''">{{ row.requestId?.slice(-10) ?? '—' }}</code></template
        >
        <template #empty><EmptyState title="Belum ada catatan audit" /></template>
    </DataTable>
</template>
