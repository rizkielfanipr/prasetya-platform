<script setup lang="ts" generic="T">
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from '@lucide/vue';
import ErrorState from './ErrorState.vue';

export interface DataTableColumn {
    key: string;
    label: string;
    align?: 'left' | 'right';
    sortable?: boolean;
    srOnlyLabel?: boolean;
}

withDefaults(
    defineProps<{
        caption: string;
        columns: DataTableColumn[];
        rows: T[];
        rowKey: (row: T) => string | number;
        loading?: boolean;
        failed?: boolean;
        sort?: string | null;
        direction?: 'asc' | 'desc' | null;
        skeletonRows?: number;
    }>(),
    { loading: false, failed: false, sort: null, direction: null, skeletonRows: 6 },
);

defineEmits<{ sort: [key: string]; retry: [] }>();

function ariaSort(active: boolean, direction: 'asc' | 'desc' | null): 'ascending' | 'descending' | 'none' {
    if (!active) {
        return 'none';
    }

    return direction === 'asc' ? 'ascending' : 'descending';
}
</script>

<template>
    <ErrorState v-if="failed" retryable @retry="$emit('retry')" />
    <div v-else class="overflow-x-auto" :aria-busy="loading" tabindex="0" role="region" :aria-label="caption">
        <Table>
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <TableHeader class="bg-muted">
                <TableRow class="hover:bg-muted">
                    <TableHead
                        v-for="(column, index) in columns"
                        :key="column.key"
                        scope="col"
                        class="h-11 px-4 text-[13px] font-semibold text-foreground"
                        :class="[column.align === 'right' ? 'text-right' : 'text-left', index === 0 ? 'sticky left-0 z-10 bg-muted' : '']"
                        :aria-sort="column.sortable ? ariaSort(sort === column.key, direction) : undefined"
                    >
                        <button v-if="column.sortable" type="button" class="inline-flex items-center gap-1 font-semibold" @click="$emit('sort', column.key)">
                            {{ column.label }}
                            <ArrowUp v-if="sort === column.key && direction === 'asc'" class="size-3.5" aria-hidden="true" />
                            <ArrowDown v-else-if="sort === column.key" class="size-3.5" aria-hidden="true" />
                            <ChevronsUpDown v-else class="size-3.5 text-muted-foreground" aria-hidden="true" />
                        </button>
                        <span v-else :class="{ 'sr-only': column.srOnlyLabel }">{{ column.label }}</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <template v-if="loading && rows.length === 0">
                    <TableRow v-for="row in skeletonRows" :key="row">
                        <TableCell v-for="column in columns" :key="column.key" class="h-[52px] px-4"><Skeleton class="h-4 w-full" /></TableCell>
                    </TableRow>
                </template>
                <template v-else>
                    <TableRow v-for="row in rows" :key="rowKey(row)" class="group hover:bg-secondary/40" :class="{ 'opacity-60': loading }">
                        <TableCell
                            v-for="(column, index) in columns"
                            :key="column.key"
                            class="h-[52px] px-4 text-[13px]"
                            :class="[
                                column.align === 'right' ? 'text-right tabular' : '',
                                index === 0 ? 'sticky left-0 z-10 bg-card font-medium group-hover:bg-secondary' : '',
                            ]"
                        >
                            <slot :name="`cell-${column.key}`" :row="row" />
                        </TableCell>
                    </TableRow>
                </template>
            </TableBody>
        </Table>
        <slot v-if="!loading && rows.length === 0" name="empty" />
    </div>
</template>
