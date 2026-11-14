<script setup lang="ts" generic="T">
import { Skeleton } from '@/components/ui/skeleton';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { ArrowDownIcon, ArrowUpIcon, ChevronUpDownIcon } from '@heroicons/vue/24/outline';
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
        /**
         * Below md, rows become stacked blocks instead of a scrolling table. Cells carry data-col, so the caller
         * arranges them with grid areas; the table keeps its semantics.
         */
        stackOnMobile?: boolean;
    }>(),
    { loading: false, failed: false, sort: null, direction: null, skeletonRows: 6, stackOnMobile: false },
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
    <div
        v-else
        class="data-table overflow-x-auto"
        :class="{ 'data-table--stack': stackOnMobile }"
        :aria-busy="loading"
        tabindex="0"
        role="region"
        :aria-label="caption"
    >
        <Table>
            <caption class="sr-only">
                {{
                    caption
                }}
            </caption>
            <TableHeader class="[&_tr]:border-0">
                <TableRow class="hover:bg-transparent">
                    <TableHead
                        v-for="(column, index) in columns"
                        :key="column.key"
                        scope="col"
                        class="h-10 bg-tint-100 px-4 text-[11px] font-extrabold tracking-[0.07em] text-sidebar-foreground uppercase first:rounded-l-lg last:rounded-r-lg"
                        :class="[column.align === 'right' ? 'text-right' : 'text-left', index === 0 ? 'sticky left-0 z-10' : '']"
                        :aria-sort="column.sortable ? ariaSort(sort === column.key, direction) : undefined"
                    >
                        <button
                            v-if="column.sortable"
                            type="button"
                            class="-mx-1.5 inline-flex h-8 items-center gap-1 rounded-md px-1.5 font-extrabold tracking-[0.07em] uppercase hover:bg-tint-200"
                            @click="$emit('sort', column.key)"
                        >
                            {{ column.label }}
                            <ArrowUpIcon v-if="sort === column.key && direction === 'asc'" class="size-3.5 stroke-2 text-tint-700" aria-hidden="true" />
                            <ArrowDownIcon v-else-if="sort === column.key" class="size-3.5 stroke-2 text-tint-700" aria-hidden="true" />
                            <ChevronUpDownIcon v-else class="size-3.5 stroke-2 text-muted-foreground" aria-hidden="true" />
                        </button>
                        <span v-else :class="{ 'sr-only': column.srOnlyLabel }">{{ column.label }}</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                <template v-if="loading && rows.length === 0">
                    <TableRow v-for="row in skeletonRows" :key="row" class="hover:bg-transparent">
                        <TableCell v-for="column in columns" :key="column.key" class="h-[60px] px-4"><Skeleton class="h-4 w-full bg-tint-100" /></TableCell>
                    </TableRow>
                </template>
                <template v-else>
                    <TableRow
                        v-for="row in rows"
                        :key="rowKey(row)"
                        class="group border-border transition-colors duration-150 hover:bg-tint-50 motion-reduce:transition-none"
                        :class="{ 'opacity-60': loading }"
                    >
                        <TableCell
                            v-for="(column, index) in columns"
                            :key="column.key"
                            :data-col="column.key"
                            class="h-[60px] px-4 text-[13px]"
                            :class="[
                                column.align === 'right' ? 'text-right tabular' : '',
                                index === 0 ? 'sticky left-0 z-10 bg-card font-medium group-hover:bg-tint-50' : '',
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

<style>
/*
 * Stacked rows when the table's own width is under 60rem (a container query, so a narrow content column stacks
 * even on a wide screen): the header stays for screen readers, each row becomes a block the caller lays out.
 */
.data-table--stack {
    container-type: inline-size;
}

@container (width < 60rem) {
    .data-table--stack thead {
        position: absolute;
        width: 1px;
        height: 1px;
        overflow: hidden;
        clip-path: inset(50%);
        white-space: nowrap;
    }

    .data-table--stack table,
    .data-table--stack tbody {
        display: block;
    }

    .data-table--stack tbody tr {
        display: grid;
        padding: 14px 4px;
        column-gap: 12px;
        row-gap: 6px;
    }

    .data-table--stack tbody td {
        position: static;
        height: auto;
        padding: 0;
        background: transparent;
    }
}
</style>
