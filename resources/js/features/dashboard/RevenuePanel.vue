<script setup lang="ts">
import EmptyState from '@/components/shared/EmptyState.vue';
import ErrorState from '@/components/shared/ErrorState.vue';
import LoadingState from '@/components/shared/LoadingState.vue';
import { brandCodes, brands } from '@/lib/brands';
import type { Brand, RevenuePoint } from '@/types/backoffice';
import { router } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, onBeforeUnmount, ref } from 'vue';

/** Daily Paid revenue. Brand and period come from the Dashboard's scope bar, not from controls here. */
const props = defineProps<{ points: RevenuePoint[] | undefined; brand: Brand | null }>();

// Same colours and marker shapes as the chart's series: circle for Webworks, diamond for Satamu (not colour alone).
const legend = computed<Brand[]>(() => (props.brand ? [props.brand] : brandCodes));
const legendMark: Record<Brand, string> = { WEBWORKS: 'rounded-full bg-chart-webworks', SATAMU: 'rotate-45 bg-chart-satamu' };

// Chart.js stays out of the dashboard entry chunk.
const RevenueChart = defineAsyncComponent(() => import('./RevenueChart.vue'));

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
    <section aria-labelledby="revenue-heading" class="min-w-0 border-t border-border pt-4">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="revenue-heading" class="text-sm font-extrabold">Pendapatan Harian</h2>
            <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[13px] text-muted-foreground">
                <span>{{ brand ? `Hanya ${brands[brand].label}` : 'Kedua brand' }}, pembayaran Paid</span>
                <!-- The legend items move together, so one never ends up alone on a line. -->
                <span class="inline-flex items-center gap-x-4 whitespace-nowrap">
                    <span v-for="code in legend" :key="code" class="inline-flex items-center gap-1.5 font-bold text-foreground">
                        <span class="size-2.5 shrink-0" :class="legendMark[code]" aria-hidden="true" />{{ brands[code].label }}
                    </span>
                </span>
            </p>
        </div>
        <ErrorState v-if="failed" retryable @retry="retry" />
        <LoadingState v-else-if="points === undefined" :rows="5" label="Memuat grafik pendapatan" />
        <EmptyState v-else-if="!hasRevenue" title="Belum ada pendapatan pada periode ini" description="Grafik muncul setelah ada pembayaran terverifikasi." />
        <Suspense v-else>
            <RevenueChart :points="points" :brand="brand" />
            <template #fallback><LoadingState :rows="5" label="Memuat grafik pendapatan" /></template>
        </Suspense>
    </section>
</template>
