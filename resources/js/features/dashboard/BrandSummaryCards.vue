<script setup lang="ts">
import { brands } from '@/lib/brands';
import { formatCurrency, formatNumber } from '@/lib/format';
import type { BrandSummary } from '@/types/backoffice';

defineProps<{ summaries: BrandSummary[] }>();
</script>

<template>
    <section aria-label="Ringkasan per brand" class="grid gap-4 md:grid-cols-2">
        <article v-for="summary in summaries" :key="summary.brand" class="rounded-xl border bg-card p-5" :data-testid="`brand-summary-${summary.brand}`">
            <h3 class="flex items-center gap-2 text-base font-bold">
                <span class="size-2.5 rounded-full" :class="brands[summary.brand].dotClass" aria-hidden="true" />
                {{ brands[summary.brand].fullName }}
            </h3>
            <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-muted-foreground">Pendapatan</dt>
                    <dd class="font-semibold tabular">{{ formatCurrency(summary.paidAmount) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Berhasil</dt>
                    <dd class="font-semibold tabular">{{ formatNumber(summary.paidCount) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Menunggu</dt>
                    <dd class="font-semibold tabular">{{ formatNumber(summary.pendingCount) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">Pesanan aktif</dt>
                    <dd class="font-semibold tabular">{{ formatNumber(summary.activeOrders) }}</dd>
                </div>
            </dl>
        </article>
    </section>
</template>
