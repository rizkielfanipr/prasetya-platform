<script setup lang="ts">
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ContentRow } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import ContentStatusBadge from './ContentStatusBadge.vue';

defineProps<{ rows: ContentRow[]; loading: boolean; failed: boolean; filtered: boolean; sort: string; direction: 'asc' | 'desc' }>();
defineEmits<{ retry: []; sort: [key: string] }>();

const columns: DataTableColumn[] = [
    { key: 'title', label: 'Judul' },
    { key: 'brand', label: 'Brand' },
    { key: 'planned_at', label: 'Rencana posting', sortable: true },
    { key: 'status', label: 'Status' },
    { key: 'revision', label: 'Revisi', align: 'right' },
    { key: 'updated_at', label: 'Diubah', sortable: true },
];
</script>

<template>
    <DataTable
        caption="Daftar konten"
        :columns="columns"
        :rows="rows"
        :row-key="(row) => row.id"
        :loading="loading"
        :failed="failed"
        :sort="sort"
        :direction="direction"
        @retry="$emit('retry')"
        @sort="$emit('sort', $event)"
    >
        <template #cell-title="{ row }">
            <Link :href="routes.contentItem(row.id)" class="line-clamp-2 min-w-48 hover:underline">{{ row.title }}</Link>
        </template>
        <template #cell-brand="{ row }"
            ><span class="whitespace-nowrap">{{ row.brand.name }}</span></template
        >
        <template #cell-planned_at="{ row }"
            ><span class="whitespace-nowrap">{{ formatDateTime(row.plannedAt) }}</span></template
        >
        <template #cell-status="{ row }"><ContentStatusBadge :status="row.status" /></template>
        <template #cell-revision="{ row }">{{ row.revisionNumber ?? '—' }}</template>
        <template #cell-updated_at="{ row }"
            ><span class="whitespace-nowrap">{{ formatDateTime(row.updatedAt) }}</span></template
        >
        <template #empty>
            <EmptyState
                :title="filtered ? 'Tidak ada konten yang cocok' : 'Belum ada konten'"
                :description="filtered ? 'Coba ubah atau hapus filter.' : 'Buat konten pertama dengan tombol di atas.'"
            />
        </template>
    </DataTable>
</template>
