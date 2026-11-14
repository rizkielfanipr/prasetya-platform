<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import AttentionView from '@/features/reconciliation/AttentionView.vue';
import PaymentEventsView from '@/features/payments/PaymentEventsView.vue';
import AllTransactionsView from '@/features/transactions/views/AllTransactionsView.vue';
import { formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import { workspaceViews } from '@/lib/workspace';
import type {
    Paginated,
    PaymentEventFilters,
    ReconciliationGroup,
    ReconciliationIssue,
    SharedProps,
    TransactionFilterOptions,
    TransactionFilters,
    TransactionRow,
    TransactionWorkspaceView,
    WebhookEventRow,
} from '@/types/backoffice';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Transaksi (ADR-0010): one workspace, three views. The server sends only the active view's props, so each view's
 * fields are optional here and read only when that view is active.
 */
const props = defineProps<
    { view: TransactionWorkspaceView } & Partial<TransactionFilterOptions> & {
            filters?: TransactionFilters & PaymentEventFilters;
            transactions?: Paginated<TransactionRow>;
            group?: ReconciliationGroup;
            counts?: Record<ReconciliationGroup, number>;
            needsAction?: ReconciliationGroup[];
            thresholds?: { pending_too_long_minutes: number; no_attempt_after_minutes: number };
            issues?: Paginated<ReconciliationIssue>;
            events?: Paginated<WebhookEventRow>;
        }
>();

const page = usePage<SharedProps>();
// The same cached count as the header bell: only groups that need action, never duplicates.
const openIssues = computed(() => page.props.openIssueCount ?? 0);
const baseQuery = computed(() => ({ view: props.view }));
</script>

<template>
    <AppLayout title="Transaksi" description="Pesanan, isu yang perlu ditinjau, dan event pembayaran dari kedua brand">
        <!-- On narrow phones the strip scrolls; the fade at its edge says there is more. -->
        <nav
            aria-label="Tampilan transaksi"
            class="flex max-w-full gap-6 overflow-x-auto border-b border-border max-sm:[mask-image:linear-gradient(to_right,black_82%,transparent)] max-sm:pr-10"
        >
            <Link
                v-for="item in workspaceViews"
                :key="item.value"
                :href="`${routes.transactions}?view=${item.value}`"
                class="-mb-px inline-flex h-11 shrink-0 items-center gap-2 border-b-[3px] text-sm font-bold whitespace-nowrap transition-colors hover:text-foreground focus-visible:rounded-t-md motion-reduce:transition-none"
                :class="item.value === view ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:border-tint-300'"
                :aria-current="item.value === view ? 'page' : undefined"
                :data-testid="`workspace-${item.value}`"
            >
                {{ item.label }}
                <span
                    v-if="item.value === 'attention' && openIssues > 0"
                    class="rounded-md bg-warning-surface px-1.5 text-[11px] leading-5 font-extrabold text-warning tabular"
                    :aria-label="`${openIssues} isu perlu ditinjau`"
                    >{{ formatNumber(openIssues) }}</span
                >
            </Link>
        </nav>

        <AllTransactionsView
            v-if="view === 'all' && filters && transactions"
            :filters="filters"
            :transactions="transactions"
            :payment-methods="paymentMethods ?? []"
            :packages="packages ?? []"
            :theme-codes="themeCodes ?? []"
            :event-types="eventTypes ?? []"
            :path="routes.transactions"
            :base-query="baseQuery"
        />
        <AttentionView
            v-else-if="view === 'attention' && group && counts && needsAction && thresholds && issues"
            :group="group"
            :counts="counts"
            :needs-action="needsAction"
            :thresholds="thresholds"
            :issues="issues"
            :path="routes.transactions"
            :base-query="baseQuery"
        />
        <PaymentEventsView
            v-else-if="view === 'events' && filters && events"
            :filters="filters"
            :events="events"
            :path="routes.transactions"
            :base-query="baseQuery"
        />
    </AppLayout>
</template>
