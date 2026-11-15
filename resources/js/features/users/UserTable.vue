<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { formatDateTime } from '@/lib/format';
import { userStatuses } from '@/lib/status';
import { routes } from '@/lib/routes';
import type { UserRow } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import UserRowActions from './UserRowActions.vue';

defineProps<{ rows: UserRow[]; loading: boolean; failed: boolean; filtered: boolean }>();
defineEmits<{ retry: []; clear: [] }>();

// The name is the row's only link and its anchor; username rides under it in mono, so "Dibuat" folds into
// "Terakhir masuk" and one column drops out (DESIGN.md Data Table).
const columns: DataTableColumn[] = [
    { key: 'name', label: 'Pengguna' },
    { key: 'status', label: 'Status' },
    { key: 'lastLoginAt', label: 'Terakhir masuk' },
    { key: 'actions', label: 'Aksi', srOnlyLabel: true, align: 'right' },
];
</script>

<template>
    <DataTable
        caption="Daftar pengguna"
        :columns="columns"
        :rows="rows"
        :row-key="(row: UserRow) => row.id"
        :loading="loading"
        :failed="failed"
        stack-on-mobile
        @retry="$emit('retry')"
    >
        <template #cell-name="{ row }">
            <div class="min-w-0">
                <Link
                    :href="routes.user(row.id)"
                    class="focus-visible:outline-line-orange font-bold text-primary-text underline decoration-tint-300 underline-offset-4 hover:decoration-tint-600 focus-visible:outline-2 focus-visible:outline-offset-2"
                >
                    {{ row.name }}
                </Link>
                <p class="font-mono text-[13px] font-semibold text-muted-foreground">@{{ row.username }}</p>
            </div>
        </template>

        <template #cell-status="{ row }">
            <div class="flex flex-wrap items-center gap-2">
                <StatusBadge :status="row.isActive ? userStatuses.active : userStatuses.inactive" />
                <span v-if="row.isSelf" class="text-[12px] font-semibold text-muted-foreground">Akun Anda</span>
            </div>
        </template>

        <template #cell-lastLoginAt="{ row }">
            <span class="text-[13px] whitespace-nowrap text-muted-foreground">{{ formatDateTime(row.lastLoginAt) }}</span>
        </template>

        <template #cell-actions="{ row }">
            <div class="flex justify-end"><UserRowActions :user="row" /></div>
        </template>

        <template #empty>
            <EmptyState
                :title="filtered ? 'Tidak ada pengguna yang cocok' : 'Belum ada pengguna'"
                :description="filtered ? 'Coba ubah atau hapus filter.' : 'Tambahkan pengguna dengan tombol di atas.'"
            >
                <button v-if="filtered" type="button" class="text-sm font-bold text-primary-text underline underline-offset-4" @click="$emit('clear')">
                    Hapus semua filter
                </button>
            </EmptyState>
        </template>
    </DataTable>
</template>
