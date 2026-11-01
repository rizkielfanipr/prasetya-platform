<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import AuditTimeline from '@/components/shared/AuditTimeline.vue';
import BrandBadge from '@/components/shared/BrandBadge.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import OrderStatusBadge from '@/components/shared/OrderStatusBadge.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import PaymentSummary from '@/components/shared/PaymentSummary.vue';
import PaymentTimeline from '@/components/shared/PaymentTimeline.vue';
import WebhookEventTable from '@/features/payments/WebhookEventTable.vue';
import AdminNotesPanel from '@/features/transactions/AdminNotesPanel.vue';
import OrderHistoryList from '@/features/transactions/OrderHistoryList.vue';
import { formatCurrency, formatDateTime, formatPhone } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { AuditEntry, TransactionDetail, WebhookEventRow } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

defineProps<{ transaction: TransactionDetail; webhooks: WebhookEventRow[] | null; audit: AuditEntry[] | null }>();
</script>

<template>
    <AppLayout :title="transaction.invoice" :description="`Referensi ${transaction.id}`">
        <Link :href="routes.transactions" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-text hover:underline">
            <ArrowLeft class="size-4" aria-hidden="true" />Kembali ke transaksi
        </Link>

        <section aria-label="Ringkasan" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border bg-card p-5">
                <p class="text-xs font-semibold text-muted-foreground">Brand</p>
                <p class="mt-2"><BrandBadge :brand="transaction.brand" /></p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <p class="text-xs font-semibold text-muted-foreground">Jumlah tagihan</p>
                <p class="mt-1 font-heading text-2xl font-bold tabular">{{ formatCurrency(transaction.amount) }}</p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <p class="text-xs font-semibold text-muted-foreground">Status pembayaran</p>
                <p class="mt-2"><PaymentStatusBadge :status="transaction.paymentStatus" /></p>
            </div>
            <div class="rounded-xl border bg-card p-5">
                <p class="text-xs font-semibold text-muted-foreground">Status pesanan</p>
                <p class="mt-2"><OrderStatusBadge :status="transaction.orderStatus" /></p>
            </div>
        </section>

        <div class="grid gap-4 lg:gap-6 xl:grid-cols-3">
            <div class="min-w-0 space-y-4 lg:space-y-6 xl:col-span-2">
                <PanelCard title="Percobaan pembayaran" :description="`${transaction.payments.length} percobaan`">
                    <EmptyState
                        v-if="transaction.payments.length === 0"
                        title="Belum ada percobaan pembayaran"
                        description="Checkout belum pernah dibuat untuk pesanan ini."
                    />
                    <div v-for="payment in transaction.payments" :key="payment.id" class="border-t py-5 first:border-t-0 first:pt-0 last:pb-0">
                        <h3 class="mb-3 text-base font-bold">Percobaan {{ payment.attempt }}</h3>
                        <PaymentSummary :payment="payment" />
                        <h4 class="mt-5 mb-3 font-sans font-semibold">Kejadian</h4>
                        <PaymentTimeline :events="payment.events" />
                    </div>
                </PanelCard>

                <PanelCard v-if="webhooks !== null" title="Riwayat webhook" flush>
                    <WebhookEventTable :rows="webhooks" compact />
                </PanelCard>
            </div>

            <div class="min-w-0 space-y-4 lg:space-y-6">
                <PanelCard title="Pelanggan">
                    <dl class="space-y-3">
                        <div>
                            <dt class="text-xs font-semibold text-muted-foreground">Nama</dt>
                            <dd>{{ transaction.customer.name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-muted-foreground">Email</dt>
                            <dd class="break-all">{{ transaction.customer.email }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-muted-foreground">Telepon</dt>
                            <dd>{{ formatPhone(transaction.customer.phone) }}</dd>
                        </div>
                    </dl>
                </PanelCard>

                <PanelCard title="Pesanan">
                    <dl class="space-y-3">
                        <div v-for="item in transaction.items" :key="item.packageCode">
                            <dt class="text-xs font-semibold text-muted-foreground">Paket (harga saat pesan)</dt>
                            <dd>
                                {{ item.packageName }} × {{ item.quantity }} · <span class="tabular">{{ formatCurrency(item.lineAmount) }}</span>
                            </dd>
                        </div>
                        <div v-for="detail in transaction.brandDetails" :key="detail.label">
                            <dt class="text-xs font-semibold text-muted-foreground">{{ detail.label }}</dt>
                            <dd class="break-words">{{ detail.value }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-muted-foreground">Dibuat</dt>
                            <dd>{{ formatDateTime(transaction.createdAt) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold text-muted-foreground">Batas bayar pesanan</dt>
                            <dd>{{ formatDateTime(transaction.expiresAt) }}</dd>
                        </div>
                    </dl>
                </PanelCard>

                <PanelCard title="Riwayat status pesanan"><OrderHistoryList :entries="transaction.statusHistory" /></PanelCard>
                <AdminNotesPanel :order-id="transaction.id" :notes="transaction.notes" />
                <PanelCard v-if="audit !== null" title="Riwayat audit"><AuditTimeline :entries="audit" /></PanelCard>
            </div>
        </div>
    </AppLayout>
</template>
