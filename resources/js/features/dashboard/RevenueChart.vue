<script setup lang="ts">
import { formatCurrency, formatDay } from '@/lib/format';
import { brands } from '@/lib/brands';
import type { Brand, RevenuePoint } from '@/types/backoffice';
import { CategoryScale, Chart, type ChartDataset, Filler, Legend, LinearScale, LineController, LineElement, PointElement, Tooltip } from 'chart.js';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale, Tooltip, Legend, Filler);

const props = defineProps<{ points: RevenuePoint[]; brand: Brand | null }>();

const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart<'line', number[], string> | null = null;

const token = (name: string): string => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
const prefersReducedMotion = (): boolean => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const series = computed<Brand[]>(() => (props.brand ? [props.brand] : ['WEBWORKS', 'SATAMU']));
const totals = computed(() => series.value.map((code) => ({ code, total: props.points.reduce((sum, point) => sum + point[code], 0) })));

// Colour follows the brand and never the series position. Marker shape carries identity too, so it is not colour alone.
const seriesStyle: Record<Brand, { color: string; pointStyle: 'circle' | 'rectRot' }> = {
    WEBWORKS: { color: '--chart-webworks', pointStyle: 'circle' },
    SATAMU: { color: '--chart-satamu', pointStyle: 'rectRot' },
};

function datasets(): ChartDataset<'line', number[]>[] {
    return series.value.map((code) => {
        const color = token(seriesStyle[code].color);

        return {
            label: brands[code].label,
            data: props.points.map((point) => point[code]),
            borderColor: color,
            backgroundColor: color,
            pointBackgroundColor: color,
            pointBorderColor: token('--card'),
            pointBorderWidth: 2,
            pointStyle: seriesStyle[code].pointStyle,
            pointRadius: props.points.length > 45 ? 0 : 4,
            pointHoverRadius: 6,
            pointHitRadius: 12,
            borderWidth: 2,
            // Monotone, because a smoothed curve must not dip below zero between days.
            cubicInterpolationMode: 'monotone',
        };
    });
}

function render(): void {
    if (!canvas.value) {
        return;
    }

    chart?.destroy();
    chart = new Chart<'line', number[], string>(canvas.value, {
        type: 'line',
        data: { labels: props.points.map((point) => formatDay(point.date)), datasets: datasets() },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: prefersReducedMotion() ? false : { duration: 200 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { usePointStyle: true, color: token('--foreground'), font: { family: 'Plus Jakarta Sans Variable', size: 12 } },
                },
                tooltip: {
                    backgroundColor: token('--foreground'),
                    callbacks: { label: (item) => `${item.dataset.label ?? ''}: ${formatCurrency(item.parsed.y)}` },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    border: { color: token('--border') },
                    ticks: { color: token('--muted-foreground'), maxRotation: 0, autoSkipPadding: 16, font: { size: 12 } },
                },
                y: {
                    beginAtZero: true,
                    grid: { color: token('--chart-grid') },
                    border: { display: false },
                    ticks: { color: token('--muted-foreground'), maxTicksLimit: 6, font: { size: 12 }, callback: (value) => formatCurrency(Number(value)) },
                },
            },
        },
    });
}

onMounted(render);
watch(() => [props.points, props.brand], render);
onBeforeUnmount(() => chart?.destroy());
</script>

<template>
    <div>
        <div class="h-72 w-full">
            <canvas
                ref="canvas"
                role="img"
                :aria-label="`Grafik pendapatan harian. ${totals.map((t) => `${brands[t.code].label}: ${formatCurrency(t.total)}`).join(', ')}.`"
                data-testid="revenue-canvas"
            />
        </div>
        <details class="mt-3">
            <summary class="text-sm font-semibold text-primary-text">Lihat data grafik sebagai tabel</summary>
            <div class="mt-2 max-h-64 overflow-auto rounded-md border" tabindex="0" role="region" aria-label="Data grafik pendapatan">
                <table class="w-full text-[13px]">
                    <caption class="sr-only">
                        Pendapatan harian per brand
                    </caption>
                    <thead class="sticky top-0 bg-muted">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left font-semibold">Tanggal</th>
                            <th v-for="code in series" :key="code" scope="col" class="px-3 py-2 text-right font-semibold">{{ brands[code].label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="point in points" :key="point.date" class="border-t">
                            <th scope="row" class="px-3 py-1.5 text-left font-normal whitespace-nowrap">{{ formatDay(point.date) }}</th>
                            <td v-for="code in series" :key="code" class="px-3 py-1.5 text-right tabular">{{ formatCurrency(point[code]) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </div>
</template>
