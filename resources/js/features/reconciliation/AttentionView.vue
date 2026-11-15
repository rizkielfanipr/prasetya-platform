<script setup lang="ts">
import TablePagination from '@/components/shared/TablePagination.vue';
import { useUrlFilters } from '@/composables/useUrlFilters';
import IssueTable from '@/features/reconciliation/IssueTable.vue';
import { formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import { attentionQueues, reconciliationGroups } from '@/lib/status';
import type { AttentionQueue, AttentionSection } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Transaksi → Perlu Ditinjau (ADR-0016): four review queues over the reconciliation groups. It only observes: nothing
 * here repairs a record. A queue of several groups (Anomali sistem) shows each group as its own section, paginated
 * separately, so other sections keep their page when one moves.
 */
const props = withDefaults(
    defineProps<{
        queue: AttentionQueue;
        queues: AttentionQueue[];
        counts: Record<AttentionQueue, number>;
        thresholds: { pending_too_long_minutes: number; no_attempt_after_minutes: number };
        sections: AttentionSection[];
        path: string;
        baseQuery?: Record<string, string>;
    }>(),
    { baseQuery: () => ({}) },
);

/** The page of every section, read from the URL, so moving one section keeps the others where they are. */
function currentPages(): Record<string, string> {
    if (typeof window === 'undefined') return {};

    return Object.fromEntries([...new URL(window.location.href).searchParams].filter(([key]) => key === 'page' || key.startsWith('page_')));
}

const { apply, loading, failed } = useUrlFilters(
    () => props.path,
    () => ({ ...props.baseQuery, queue: props.queue, ...currentPages() }),
    ['sections', 'counts', 'queues', 'queue'],
);

const total = computed(() => Object.values(props.counts).reduce((sum, count) => sum + count, 0));
const current = computed(() => attentionQueues[props.queue]);
const multiple = computed(() => props.sections.length > 1 || props.queue === 'system');
const thresholdNote = computed(() => (props.queue === 'waiting' ? `Ambang: ${props.thresholds.pending_too_long_minutes} menit sejak pesanan dibuat.` : null));

const href = (key: AttentionQueue): string => `${props.path}?${new URLSearchParams({ ...props.baseQuery, queue: key }).toString()}`;
</script>

<template>
    <p class="text-sm text-muted-foreground" data-testid="attention-total">
        <b class="font-extrabold text-foreground tabular">{{ formatNumber(total) }}</b> isu menunggu keputusan Anda. Halaman ini hanya mengamati dan tidak
        memperbaiki data secara otomatis.
    </p>

    <nav aria-label="Antrean tinjauan">
        <ul class="flex flex-wrap gap-1">
            <li v-for="key in queues" :key="key">
                <Link
                    :href="href(key)"
                    preserve-scroll
                    class="inline-flex h-11 items-center gap-2 rounded-md px-3 text-sm font-bold transition-colors duration-150 motion-reduce:transition-none"
                    :class="key === queue ? 'bg-tint-100 font-extrabold text-foreground' : 'text-sidebar-foreground hover:bg-tint-50'"
                    :aria-current="key === queue ? 'true' : undefined"
                    :data-testid="`queue-${key}`"
                >
                    {{ attentionQueues[key].label }}
                    <span
                        class="rounded-md px-1.5 text-[11px] leading-5 font-extrabold tabular"
                        :class="counts[key] > 0 ? 'bg-warning-surface text-warning' : 'bg-tint-200 text-tint-800'"
                        >{{ formatNumber(counts[key]) }}</span
                    >
                </Link>
            </li>
        </ul>
    </nav>

    <section aria-labelledby="queue-heading" class="border-t border-border pt-4">
        <h2 id="queue-heading" class="text-sm font-extrabold">{{ current.label }}</h2>
        <p class="mt-1 max-w-[70ch] text-sm text-muted-foreground">
            {{ current.description }}<template v-if="thresholdNote"> {{ thresholdNote }}</template>
        </p>

        <template v-if="!multiple">
            <div v-for="section in sections" :key="section.group" class="mt-3">
                <IssueTable :caption="current.label" :rows="section.issues.data" :loading="loading" :failed="failed" @retry="apply({})" />
                <TablePagination :meta="section.issues.meta" :disabled="loading" @change="apply({ [section.pageName]: String($event) })" />
            </div>
        </template>
        <template v-else>
            <p v-if="sections.length === 0" class="mt-3 text-sm font-semibold text-success">Kosong. Tidak ada anomali sistem saat ini.</p>
            <section
                v-for="section in sections"
                :key="section.group"
                :aria-labelledby="`section-${section.group}`"
                class="mt-5"
                :data-testid="`section-${section.group}`"
            >
                <h3 :id="`section-${section.group}`" class="text-[13px] font-extrabold">
                    {{ reconciliationGroups[section.group].label }}
                    <span class="font-semibold text-muted-foreground tabular">({{ formatNumber(section.issues.meta.total) }})</span>
                </h3>
                <p class="text-[13px] text-muted-foreground">{{ reconciliationGroups[section.group].description }}</p>
                <IssueTable
                    class="mt-2"
                    :caption="reconciliationGroups[section.group].label"
                    :rows="section.issues.data"
                    :loading="loading"
                    :failed="failed"
                    @retry="apply({})"
                />
                <TablePagination :meta="section.issues.meta" :disabled="loading" @change="apply({ [section.pageName]: String($event) })" />
            </section>
        </template>
    </section>

    <p class="border-t border-border pt-4 text-[13px] text-muted-foreground">
        Tidak dihitung di sini: pesanan yang belum checkout ada di
        <Link :href="`${routes.transactions}?payment_status=NONE`" class="font-bold text-primary-text underline underline-offset-4"
            >Semua, filter No Payment</Link
        >, dan webhook duplikat yang sudah ditangani aman ada di
        <Link :href="`${routes.gatewayLog()}?status=DUPLICATE`" class="font-bold text-primary-text underline underline-offset-4">Log Gateway</Link>.
    </p>
</template>
