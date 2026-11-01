<script setup lang="ts">
import PanelCard from '@/components/layout/PanelCard.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import ErrorState from '@/components/shared/ErrorState.vue';
import LoadingState from '@/components/shared/LoadingState.vue';
import { brandCodes, brands } from '@/lib/brands';
import type { Brand, RevenuePoint } from '@/types/backoffice';
import { router } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, onBeforeUnmount, ref } from 'vue';

const props = defineProps<{ points: RevenuePoint[] | undefined; brand: Brand | null; period: string; customRange: boolean }>();
const emit = defineEmits<{ change: [changes: Record<string, string | null>] }>();

// Chart.js stays out of the dashboard entry chunk.
const RevenueChart = defineAsyncComponent(() => import('./RevenueChart.vue'));

const brandTabs: { value: Brand | null; label: string }[] = [
    { value: null, label: 'Semua' },
    ...brandCodes.map((value) => ({ value, label: brands[value].label })),
];
const periods = [
    { value: '7', label: '7 hari terakhir' },
    { value: '30', label: '30 hari terakhir' },
    { value: '90', label: '90 hari terakhir' },
];

const hasRevenue = computed(() => props.points?.some((point) => point.WEBWORKS > 0 || point.SATAMU > 0) ?? false);

// A deferred prop that fails would otherwise leave the skeleton up forever.
const failed = ref(false);
const stopHttp = router.on('httpException', () => {
    if (props.points === undefined) failed.value = true;
});
const stopNetwork = router.on('networkError', () => {
    if (props.points === undefined) failed.value = true;
});
onBeforeUnmount(() => {
    stopHttp();
    stopNetwork();
});

function retry(): void {
    failed.value = false;
    router.reload({ only: ['revenue'] });
}
</script>

<template>
    <PanelCard title="Pendapatan">
        <template #actions>
            <div class="flex flex-wrap items-center gap-2">
                <div role="group" aria-label="Filter brand grafik" class="inline-flex rounded-md border bg-muted p-0.5">
                    <button
                        v-for="tab in brandTabs"
                        :key="tab.label"
                        type="button"
                        class="rounded-sm px-3.5 py-2 text-sm font-medium transition-colors duration-150"
                        :class="brand === tab.value ? 'bg-primary-strong text-primary-foreground' : 'hover:bg-card'"
                        :aria-pressed="brand === tab.value"
                        @click="emit('change', { brand: tab.value })"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <label class="sr-only" for="revenue-period">Periode</label>
                <select
                    id="revenue-period"
                    class="h-10 rounded-md border border-input bg-card px-3 text-sm font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :value="customRange ? '' : period"
                    @change="emit('change', { period: ($event.target as HTMLSelectElement).value, date_from: null, date_to: null })"
                >
                    <option v-if="customRange" value="" disabled>Rentang khusus</option>
                    <option v-for="option in periods" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
        </template>

        <ErrorState v-if="failed" retryable @retry="retry" />
        <LoadingState v-else-if="points === undefined" :rows="5" label="Memuat grafik pendapatan" />
        <EmptyState v-else-if="!hasRevenue" title="Belum ada pendapatan pada periode ini" description="Grafik muncul setelah ada pembayaran terverifikasi." />
        <Suspense v-else>
            <RevenueChart :points="points" :brand="brand" />
            <template #fallback><LoadingState :rows="5" label="Memuat grafik pendapatan" /></template>
        </Suspense>
    </PanelCard>
</template>
