<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import BrandBadge from '@/components/shared/BrandBadge.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import PaymentRoute from '@/components/shared/PaymentRoute.vue';
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import AdminNotesPanel from '@/features/transactions/AdminNotesPanel.vue';
import PaymentAttemptCard from '@/features/transactions/PaymentAttemptCard.vue';
import TransactionActivityTimeline from '@/features/transactions/TransactionActivityTimeline.vue';
import TransactionIssuesPanel from '@/features/transactions/TransactionIssuesPanel.vue';
import { formatCurrency, formatDateTime, formatPhone } from '@/lib/format';
import { routes } from '@/lib/routes';
import { orderStatuses } from '@/lib/status';
import type { TransactionActivityEntry, TransactionDetail, TransactionIssue } from '@/types/backoffice';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * One transaction on one page (ADR-0016): the head answers "paid or not, how much", open issues sit right under it,
 * then the attempts and one Riwayat beside the order, customer, and notes. No tabs: an invoice has few events, and
 * tabs would hide the issues. Everything here belongs to this order only (ADR-0010).
 */
const props = defineProps<{
    transaction: TransactionDetail;
    reconciliationIssues: TransactionIssue[];
    activity: TransactionActivityEntry[];
}>();

const order = computed(() => orderStatuses[props.transaction.orderStatus]);
const label = 'text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase';
</script>

<template>
    <AppLayout :title="transaction.invoice" :description="`Referensi ${transaction.id}`">
        <Link :href="routes.transactions" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-text hover:underline">
            <ArrowLeftIcon class="size-4 stroke-2" aria-hidden="true" />Kembali ke transaksi
        </Link>

        <section aria-label="Status transaksi" class="rounded-xl bg-tint-50 px-5 py-5 md:px-7" data-testid="transaction-head">
            <div class="flex flex-wrap items-end justify-between gap-x-8 gap-y-3">
                <div class="min-w-0">
                    <PaymentRoute :status="transaction.paymentStatus" />
                    <p class="mt-2 text-2xl leading-tight font-extrabold tabular" data-testid="transaction-amount">{{ formatCurrency(transaction.amount) }}</p>
                </div>
                <BrandBadge :brand="transaction.brand" />
            </div>
            <p class="mt-3 text-[13px] text-muted-foreground">
                <span class="font-semibold" :class="order.tone === 'danger' ? 'text-danger' : 'text-sidebar-foreground'">Pesanan {{ order.label }}</span
                ><SeparatorDot /><span class="whitespace-nowrap">dibuat {{ formatDateTime(transaction.createdAt) }}</span
                ><template v-if="transaction.confirmedAt"
                    ><SeparatorDot /><span class="whitespace-nowrap">dikonfirmasi {{ formatDateTime(transaction.confirmedAt) }}</span></template
                ><template v-else-if="transaction.expiresAt"
                    ><SeparatorDot /><span class="whitespace-nowrap">batas bayar {{ formatDateTime(transaction.expiresAt) }}</span></template
                >
            </p>
        </section>

        <TransactionIssuesPanel :issues="reconciliationIssues" />

        <div class="detail-grid">
            <section aria-labelledby="payments-heading" class="detail-payments border-t border-border pt-4">
                <h2 id="payments-heading" class="text-sm font-extrabold">
                    Pembayaran <span class="font-semibold text-muted-foreground">({{ transaction.payments.length }} percobaan)</span>
                </h2>
                <EmptyState
                    v-if="transaction.payments.length === 0"
                    class="mt-3"
                    title="Belum ada percobaan pembayaran"
                    description="Pelanggan belum checkout untuk pesanan ini."
                />
                <div v-else class="mt-2"><PaymentAttemptCard v-for="payment in transaction.payments" :key="payment.id" :payment="payment" /></div>
            </section>

            <section aria-labelledby="history-heading" class="detail-history border-t border-border pt-4">
                <h2 id="history-heading" class="text-sm font-extrabold">Riwayat</h2>
                <p class="mt-0.5 text-[13px] text-muted-foreground">Status pesanan, pembayaran, webhook, catatan, dan audit, terbaru di atas.</p>
                <div class="mt-2"><TransactionActivityTimeline :entries="activity" /></div>
            </section>

            <section aria-labelledby="order-heading" class="detail-order border-t border-border pt-4">
                <h2 id="order-heading" class="text-sm font-extrabold">Pesanan</h2>
                <dl class="mt-3 grid gap-3">
                    <div v-for="item in transaction.items" :key="item.packageCode">
                        <dt :class="label">Paket (harga saat pesan)</dt>
                        <dd class="mt-0.5 text-sm">
                            {{ item.packageName }} × {{ item.quantity }}<SeparatorDot /><span class="font-semibold tabular">{{
                                formatCurrency(item.lineAmount)
                            }}</span>
                        </dd>
                    </div>
                    <div v-for="detail in transaction.brandDetails" :key="detail.label">
                        <dt :class="label">{{ detail.label }}</dt>
                        <dd class="mt-0.5 text-sm break-words">{{ detail.value }}</dd>
                    </div>
                    <div>
                        <dt :class="label">Status pesanan</dt>
                        <dd class="mt-0.5 text-sm font-semibold" :class="order.tone === 'danger' ? 'text-danger' : 'text-sidebar-foreground'">
                            {{ order.label }}
                        </dd>
                    </div>
                    <div>
                        <dt :class="label">Batas bayar pesanan</dt>
                        <dd class="mt-0.5 text-sm tabular">{{ formatDateTime(transaction.expiresAt) }}</dd>
                    </div>
                </dl>
            </section>

            <section aria-labelledby="customer-heading" class="detail-customer border-t border-border pt-4">
                <h2 id="customer-heading" class="text-sm font-extrabold">Pelanggan</h2>
                <dl class="mt-3 grid gap-3">
                    <div>
                        <dt :class="label">Nama</dt>
                        <dd class="mt-0.5 text-sm font-semibold">{{ transaction.customer.name }}</dd>
                    </div>
                    <div>
                        <dt :class="label">Email</dt>
                        <dd class="mt-0.5 text-sm break-all">{{ transaction.customer.email }}</dd>
                    </div>
                    <div>
                        <dt :class="label">Telepon</dt>
                        <dd class="mt-0.5 text-sm tabular">{{ formatPhone(transaction.customer.phone) }}</dd>
                    </div>
                </dl>
            </section>

            <AdminNotesPanel class="detail-notes" :order-id="transaction.id" :notes="transaction.notes" />
        </div>
    </AppLayout>
</template>

<style>
/*
 * One column on phones and tablets, in reading order: payments, order, customer, history, notes. From xl the work
 * (payments, then history) takes two thirds and the reference facts (order, customer, notes) the right third.
 */
.detail-grid {
    display: grid;
    gap: 24px;
    grid-template-areas: 'payments' 'order' 'customer' 'history' 'notes';
}

.detail-payments {
    grid-area: payments;
}

.detail-history {
    grid-area: history;
}

.detail-order {
    grid-area: order;
}

.detail-customer {
    grid-area: customer;
}

.detail-notes {
    grid-area: notes;
}

@media (width >= 80rem) {
    .detail-grid {
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
        grid-template-rows: auto auto auto auto 1fr;
        /* Order spans the first two rows, so Riwayat starts right under the attempts, not under the order facts. */
        grid-template-areas:
            'payments order'
            'history order'
            'history customer'
            'history notes'
            'history .';
        column-gap: 40px;
        align-items: start;
    }
}
</style>
