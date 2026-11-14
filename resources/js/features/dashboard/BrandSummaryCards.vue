<script setup lang="ts">
import BrandBadge from '@/components/shared/BrandBadge.vue';
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import { brands } from '@/lib/brands';
import { formatCurrency, formatNumber } from '@/lib/format';
import type { Brand, BrandSummary } from '@/types/backoffice';
import { computed } from 'vue';

/**
 * Dua Brand: both brands side by side for the same period, whatever the brand filter says (the server always
 * returns both). The bar is each brand's share of the combined Paid revenue, in the brand's own form colour.
 */
const props = defineProps<{ summaries: BrandSummary[] }>();

const total = computed(() => props.summaries.reduce((sum, summary) => sum + summary.paidAmount, 0));
const share = (amount: number): number => (total.value > 0 ? Math.round((amount / total.value) * 100) : 0);
// Series colours from DESIGN.md: coral is Satamu's outline and dot only, so its bar uses the chart token.
const barClass: Record<Brand, string> = { WEBWORKS: 'bg-chart-webworks', SATAMU: 'bg-chart-satamu' };
</script>

<template>
    <section aria-labelledby="brands-heading" class="border-t border-border pt-4">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="brands-heading" class="text-sm font-extrabold">Dua Brand</h2>
            <p class="text-[13px] text-muted-foreground">Selalu kedua brand</p>
        </div>
        <ul class="space-y-4">
            <li v-for="summary in summaries" :key="summary.brand" :data-testid="`brand-summary-${summary.brand}`">
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                    <span class="inline-flex items-center gap-2"
                        ><BrandBadge :brand="summary.brand" compact /><span class="text-sm font-bold">{{ brands[summary.brand].fullName }}</span></span
                    >
                    <span class="text-sm font-extrabold tabular">{{ formatCurrency(summary.paidAmount) }}</span>
                </div>
                <div
                    class="mt-2 h-2 overflow-hidden rounded-full bg-tint-100"
                    role="img"
                    :aria-label="`${brands[summary.brand].label}: ${share(summary.paidAmount)}% dari pendapatan Paid kedua brand`"
                >
                    <div class="h-full rounded-full" :class="barClass[summary.brand]" :style="{ width: `${share(summary.paidAmount)}%` }" />
                </div>
                <p class="mt-2 text-[13px] text-muted-foreground tabular">
                    {{ share(summary.paidAmount) }}%<SeparatorDot />{{ formatNumber(summary.paidCount) }} Paid<SeparatorDot />{{
                        formatNumber(summary.pendingCount)
                    }}
                    Pending<SeparatorDot />{{ formatNumber(summary.activeOrders) }} pesanan aktif
                </p>
            </li>
        </ul>
    </section>
</template>
