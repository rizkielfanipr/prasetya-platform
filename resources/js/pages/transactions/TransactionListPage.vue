<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import AllTransactionsView from '@/features/transactions/views/AllTransactionsView.vue';
import { routes } from '@/lib/routes';
import type { Paginated, TransactionFilterOptions, TransactionFilters, TransactionRow } from '@/types/backoffice';

// The pre-workspace list page, kept so a rollback can point /transactions back here (ADR-0010). Not rendered today.
defineProps<TransactionFilterOptions & { filters: TransactionFilters; transactions: Paginated<TransactionRow> }>();
</script>

<template>
    <AppLayout title="Transaksi" description="Pesanan dan pembayaran dari kedua brand">
        <AllTransactionsView
            :filters="filters"
            :transactions="transactions"
            :payment-methods="paymentMethods"
            :packages="packages"
            :theme-codes="themeCodes"
            :event-types="eventTypes"
            :path="routes.transactions"
        />
    </AppLayout>
</template>
