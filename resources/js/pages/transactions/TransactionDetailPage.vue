<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import BrandBadge from '@/components/shared/BrandBadge.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import OrderStatusBadge from '@/components/shared/OrderStatusBadge.vue';
import PaymentStatusBadge from '@/components/shared/PaymentStatusBadge.vue';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import WebhookEventTable from '@/features/payments/WebhookEventTable.vue';
import AdminNotesPanel from '@/features/transactions/AdminNotesPanel.vue';
import PaymentAttemptCard from '@/features/transactions/PaymentAttemptCard.vue';
import TransactionActivityTimeline from '@/features/transactions/TransactionActivityTimeline.vue';
import TransactionIssuesPanel from '@/features/transactions/TransactionIssuesPanel.vue';
import { formatCurrency, formatDateTime, formatPhone } from '@/lib/format';
import { routes } from '@/lib/routes';
import { transactionDetailTabs, type TransactionDetailTab } from '@/lib/transaction-tabs';
import type { TransactionActivityEntry, TransactionDetail, TransactionIssue, WebhookEventRow } from '@/types/backoffice';
import { Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    transaction: TransactionDetail;
    webhooks: WebhookEventRow[];
    reconciliationIssues: TransactionIssue[];
    activity: TransactionActivityEntry[];
}>();

const page = usePage();
const tab = ref<TransactionDetailTab>(transactionDetailTabs.fromUrl(page.url));

// Keep the tab in the URL (bookmarks, and the redirect back after Cek status) without adding history entries.
watch(tab, (value) => {
    const url = new URL(window.location.href);
    if (value === 'summary') url.searchParams.delete('tab');
    else url.searchParams.set('tab', value);
    window.history.replaceState(window.history.state, '', url);
});

const openIssues = computed(() => props.reconciliationIssues.filter((issue) => issue.needsAction).length);
</script>

<template>
    <AppLayout :title="transaction.invoice" :description="`Referensi ${transaction.id}`">
        <Link :href="routes.transactions" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-text hover:underline">
            <ArrowLeft class="size-4" aria-hidden="true" />Kembali ke transaksi
        </Link>

        <section aria-label="Status transaksi" class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
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

        <Tabs v-model="tab">
            <TabsList aria-label="Bagian detail transaksi">
                <TabsTrigger value="summary">Ringkasan</TabsTrigger>
                <TabsTrigger value="payments">
                    Pembayaran
                    <span
                        v-if="openIssues > 0"
                        class="rounded-full bg-warning-surface px-2 py-0.5 text-[11px] font-bold text-warning tabular"
                        :aria-label="`${openIssues} isu perlu ditinjau`"
                        >{{ openIssues }}</span
                    >
                </TabsTrigger>
                <TabsTrigger value="activity">Aktivitas</TabsTrigger>
            </TabsList>

            <TabsContent value="summary">
                <div class="grid gap-4 lg:gap-6 xl:grid-cols-3">
                    <div class="min-w-0 space-y-4 lg:space-y-6 xl:col-span-2">
                        <PanelCard title="Pesanan">
                            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
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
                        <AdminNotesPanel :order-id="transaction.id" :notes="transaction.notes" />
                    </div>
                    <div class="min-w-0">
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
                    </div>
                </div>
            </TabsContent>

            <TabsContent value="payments" class="space-y-4 lg:space-y-6">
                <TransactionIssuesPanel :issues="reconciliationIssues" />
                <PanelCard title="Percobaan pembayaran" :description="`${transaction.payments.length} percobaan`">
                    <EmptyState
                        v-if="transaction.payments.length === 0"
                        title="Belum ada percobaan pembayaran"
                        description="Checkout belum pernah dibuat untuk pesanan ini."
                    />
                    <PaymentAttemptCard v-for="payment in transaction.payments" :key="payment.id" :payment="payment" />
                </PanelCard>
                <PanelCard title="Riwayat webhook" flush>
                    <WebhookEventTable :rows="webhooks" compact />
                </PanelCard>
            </TabsContent>

            <TabsContent value="activity">
                <PanelCard title="Aktivitas" description="Status pesanan, pembayaran, webhook, catatan, dan audit transaksi ini, terbaru di atas.">
                    <TransactionActivityTimeline :entries="activity" />
                </PanelCard>
            </TabsContent>
        </Tabs>
    </AppLayout>
</template>
