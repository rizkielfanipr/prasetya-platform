<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { formatDateTime } from '@/lib/format';
import { userStatuses } from '@/lib/status';
import type { UserRow } from '@/types/backoffice';
import UserRowActions from './UserRowActions.vue';

defineProps<{ rows: UserRow[]; loading: boolean; failed: boolean; filtered: boolean }>();
defineEmits<{ retry: [] }>();

const columns: DataTableColumn[] = [
    { key: 'name', label: 'Nama' },
    { key: 'username', label: 'Username' },
    { key: 'status', label: 'Status' },
    { key: 'lastLogin', label: 'Masuk terakhir' },
    { key: 'created', label: 'Dibuat' },
    { key: 'actions', label: 'Aksi', srOnlyLabel: true, align: 'right' },
];
</script>

<template>
    <DataTable caption="Daftar pengguna" :columns="columns" :rows="rows" :row-key="(row) => row.id" :loading="loading" :failed="failed" @retry="$emit('retry')">
        <template #cell-name="{ row }">
            <span class="whitespace-nowrap">{{ row.name }}</span>
            <span v-if="row.isSelf" class="ml-1 text-xs font-normal text-muted-foreground">(Anda)</span>
        </template>
        <template #cell-username="{ row }"
            ><span class="font-mono text-xs">{{ row.username }}</span></template
        >
        <template #cell-status="{ row }"><StatusBadge :status="row.isActive ? userStatuses.active : userStatuses.inactive" /></template>
        <template #cell-lastLogin="{ row }"
            ><span class="whitespace-nowrap">{{ formatDateTime(row.lastLoginAt) }}</span></template
        >
        <template #cell-created="{ row }"
            ><span class="whitespace-nowrap">{{ formatDateTime(row.createdAt) }}</span></template
        >
        <template #cell-actions="{ row }"><UserRowActions :user="row" /></template>
        <template #empty>
            <EmptyState
                :title="filtered ? 'Tidak ada pengguna yang cocok' : 'Belum ada pengguna'"
                :description="filtered ? 'Coba ubah atau hapus filter.' : 'Tambahkan pengguna dengan tombol di atas.'"
            />
        </template>
    </DataTable>
</template>
