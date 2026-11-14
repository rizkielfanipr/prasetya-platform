<script setup lang="ts">
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import DashboardMeta from './DashboardMeta.vue';
import { formatChange, formatCurrency, formatNumber } from '@/lib/format';
import { paymentStatuses } from '@/lib/status';
import { routes } from '@/lib/routes';
import type { Brand, DashboardSummary, MetricPair, PaymentStatus } from '@/types/backoffice';
import { ArrowRightIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';

/**
 * The Dashboard's head (DESIGN.md signature, at page scale). The main line runs Pending → Paid in reading order, so
 * revenue is answered at its end. Failed/Expired is a dashed spur leaving that line and ending at a × terminus: the
 * × always ends a line and never starts one, and nothing joins Paid to it. The summary has no Created count, so
 * there is no Created station.
 */
const props = defineProps<{ summary: DashboardSummary; brand: Brand | null; comparison: string }>();

function listOf(status: PaymentStatus): string {
    const query = new URLSearchParams({ payment_status: status });
    if (props.brand) query.set('brand', props.brand);

    return `${routes.transactions}?${query.toString()}`;
}

function change(pair: MetricPair): string {
    const delta = formatChange(pair.value, pair.previous);
    if (delta.direction === 'flat') return `sama seperti ${props.comparison}`;
    // formatChange says "Baru" when nothing came before; spell that out instead.
    if (pair.previous === 0) return `belum ada pada ${props.comparison}`;

    return `${delta.label} dibanding ${props.comparison}`;
}
</script>

<template>
    <section aria-labelledby="payment-route-heading" class="route rounded-xl bg-tint-50 px-5 py-5 md:px-7 lg:py-4">
        <h2 id="payment-route-heading" class="sr-only">Perjalanan pembayaran</h2>
        <ol class="contents">
            <li class="route-stop route-stop--pending">
                <span class="route-marker border-[5px] border-warning bg-background" aria-hidden="true" />
                <p class="text-2xl leading-tight font-extrabold tabular">
                    <span class="text-warning">{{ paymentStatuses.PENDING.label }}</span
                    ><span class="ml-2 whitespace-nowrap">{{ formatNumber(summary.pending.value) }}</span>
                </p>
                <p class="text-[13px] text-muted-foreground">
                    <span class="whitespace-nowrap">Menunggu bayar<SeparatorDot /></span
                    ><span class="whitespace-nowrap">sebelumnya {{ formatNumber(summary.pending.previous) }}</span>
                </p>
                <Link :href="listOf('PENDING')" class="route-link">Lihat transaksi Pending<ArrowRightIcon class="size-3.5 stroke-2" aria-hidden="true" /></Link>
            </li>

            <li class="route-stop route-stop--spur">
                <span class="route-marker route-marker--terminus" aria-hidden="true" />
                <p class="text-2xl leading-tight font-extrabold tabular">
                    <span class="text-danger">{{ paymentStatuses.FAILED.label }}/<wbr />{{ paymentStatuses.EXPIRED.label }}</span
                    ><span class="ml-2 whitespace-nowrap">{{ formatNumber(summary.failedOrExpired.value) }}</span>
                </p>
                <p class="text-[13px] text-muted-foreground">
                    <span class="whitespace-nowrap">Berhenti di terminus<SeparatorDot /></span
                    ><span class="whitespace-nowrap">sebelumnya {{ formatNumber(summary.failedOrExpired.previous) }}</span>
                </p>
                <p class="flex flex-wrap gap-x-4">
                    <Link :href="listOf('FAILED')" class="route-link">Lihat Failed<ArrowRightIcon class="size-3.5 stroke-2" aria-hidden="true" /></Link>
                    <Link :href="listOf('EXPIRED')" class="route-link">Lihat Expired<ArrowRightIcon class="size-3.5 stroke-2" aria-hidden="true" /></Link>
                </p>
            </li>

            <li class="route-stop route-stop--paid">
                <span class="route-marker bg-success ring-[7px] ring-tint-100" aria-hidden="true" />
                <p class="text-2xl leading-tight font-extrabold tabular">
                    <span class="text-success">{{ paymentStatuses.PAID.label }}</span
                    ><span class="ml-2 whitespace-nowrap" data-testid="metric-value">{{ formatCurrency(summary.totalPaid.value) }}</span>
                </p>
                <p class="text-[13px] text-muted-foreground">
                    <span class="whitespace-nowrap">Pendapatan masuk dari {{ formatNumber(summary.successful.value) }} transaksi<SeparatorDot /></span
                    ><span>{{ change(summary.totalPaid) }}</span>
                </p>
                <Link :href="listOf('PAID')" class="route-link">Lihat transaksi Paid<ArrowRightIcon class="size-3.5 stroke-2" aria-hidden="true" /></Link>
            </li>
        </ol>

        <DashboardMeta class="route-meta flex lg:hidden" :active-orders="summary.activeOrders" :brand="brand" :comparison="comparison" />
    </section>
</template>

<style>
/*
 * Below lg: one vertical rail (x = 13 px) from Pending down to Paid; the terminus sits indented between them, joined
 * to the rail by a dashed elbow that ends at its ×. From lg: the rail runs across the top from Pending to Paid, and
 * the terminus hangs below it on a dashed drop. Pending, terminus, and Paid keep the same DOM order everywhere.
 */
.route {
    display: grid;
    row-gap: 24px;
}

.route-stop {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
    padding-left: 46px;
}

.route-marker {
    position: absolute;
    z-index: 1;
    top: 0;
    left: 0;
    width: 30px;
    height: 30px;
    border-radius: 9999px;
}

.route-marker--terminus::before,
.route-marker--terminus::after {
    content: '';
    position: absolute;
    top: -1px;
    left: 13px;
    width: 4px;
    height: 32px;
    border-radius: 9999px;
    background: var(--danger);
    transform: rotate(45deg);
}

.route-marker--terminus::after {
    transform: rotate(-45deg);
}

/* Vertical rail: from Pending's marker, through the terminus row, to Paid's marker. */
.route-stop--pending::after,
.route-stop--spur::before {
    content: '';
    position: absolute;
    left: 13px;
    width: 4px;
    border-radius: 9999px;
    background: var(--tint-400);
}

.route-stop--pending::after {
    top: 38px;
    bottom: -24px;
}

.route-stop--spur::before {
    top: 0;
    bottom: -20px;
}

/* The spur: a dashed elbow off the rail, ending at the terminus. */
.route-stop--spur {
    padding-left: 92px;
}

.route-stop--spur .route-marker {
    left: 46px;
}

.route-stop--spur::after {
    content: '';
    position: absolute;
    top: 13px;
    left: 19px;
    width: 23px;
    height: 4px;
    border-radius: 9999px;
    background: repeating-linear-gradient(90deg, var(--tint-400) 0 6px, transparent 6px 11px);
}

.route-meta {
    border-top: 1px solid var(--tint-200);
    padding-top: 16px;
}

/* A 44 px hit area without 44 px of layout height: the padding is taken back by the negative margin. */
.route-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-block: -8px -10px;
    padding-block: 10px;
    font-size: 13px;
    font-weight: 700;
    color: var(--primary-text);
    text-decoration: underline;
    text-decoration-color: var(--tint-300);
    text-decoration-thickness: 2px;
    text-underline-offset: 4px;
}

.route-link:hover,
.route-link:focus-visible {
    text-decoration-color: var(--tint-600);
}

@media (width >= 64rem) {
    .route {
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.35fr);
        grid-template-areas: 'pending spur paid';
        align-items: start;
        column-gap: 24px;
        row-gap: 16px;
    }

    .route-stop--pending {
        grid-area: pending;
    }

    .route-stop--spur {
        grid-area: spur;
    }

    .route-stop--paid {
        grid-area: paid;
    }

    .route-stop {
        padding-top: 42px;
        padding-left: 0;
    }

    /* The rail across the top: Pending's segment, then over the spur column to Paid. */
    .route-stop--pending::after,
    .route-stop--spur::before {
        top: 13px;
        bottom: auto;
        height: 4px;
        width: auto;
    }

    .route-stop--pending::after {
        left: 40px;
        right: -24px;
    }

    .route-stop--spur::before {
        left: 0;
        right: -16px;
    }

    /* The spur drops from the rail to the terminus below it. */
    .route-stop--spur {
        padding-top: 84px;
        padding-left: 0;
    }

    .route-stop--spur .route-marker {
        top: 42px;
        left: 0;
    }

    .route-stop--spur::after {
        top: 19px;
        left: 13px;
        width: 4px;
        height: 21px;
        background: repeating-linear-gradient(180deg, var(--tint-400) 0 6px, transparent 6px 11px);
    }
}
</style>
