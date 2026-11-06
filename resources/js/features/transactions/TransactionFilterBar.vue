<script setup lang="ts">
import DateRangeFilter from '@/components/shared/DateRangeFilter.vue';
import FilterBar, { type ActiveFilter } from '@/components/shared/FilterBar.vue';
import SearchInput from '@/components/shared/SearchInput.vue';
import SelectFilter from '@/components/shared/SelectFilter.vue';
import { Input } from '@/components/ui/input';
import { brandCodes, brands } from '@/lib/brands';
import { orderStatuses, paymentStatuses } from '@/lib/status';
import type { Brand, OrderStatus, PaymentStatus, TransactionFilterOptions, TransactionFilters } from '@/types/backoffice';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ filters: TransactionFilters; options: TransactionFilterOptions }>();
const emit = defineEmits<{ change: [changes: Partial<Record<keyof TransactionFilters | 'page', string | null>>]; search: [value: string | null] }>();

const search = ref(props.filters.search ?? '');
watch(search, (value) => {
    if (value !== (props.filters.search ?? '')) {
        emit('search', value || null);
    }
});
watch(
    () => props.filters.search,
    (value) => {
        if ((value ?? '') !== search.value) {
            search.value = value ?? '';
        }
    },
);

const brandOptions = brandCodes.map((value) => ({ value, label: brands[value].label }));
const paymentOptions = (Object.keys(paymentStatuses) as PaymentStatus[]).map((value) => ({ value, label: paymentStatuses[value].label }));
const orderOptions = (Object.keys(orderStatuses) as OrderStatus[]).map((value) => ({ value, label: orderStatuses[value].label }));
const plain = (values: string[]) => values.map((value) => ({ value, label: value }));
const methodOptions = computed(() => plain(props.options.paymentMethods));
// With a brand chosen, only that brand's packages; otherwise all, labelled by brand.
const packageOptions = computed(() =>
    props.options.packages
        .filter((option) => !props.filters.brand || option.brand === props.filters.brand)
        .map((option) => ({ value: option.value, label: props.filters.brand ? option.label : `${option.label} · ${brands[option.brand].label}` })),
);
const packageLabel = (code: string): string => props.options.packages.find((option) => option.value === code)?.label ?? code;
const themeOptions = computed(() => plain(props.options.themeCodes));
const eventTypeOptions = computed(() => plain(props.options.eventTypes));
// Theme and event filters only mean something for invitation orders.
const showInvitationFilters = computed(() => props.filters.brand !== 'WEBWORKS');

const active = computed<ActiveFilter[]>(() => {
    const f = props.filters;

    return [
        f.search ? { key: 'search', label: `Cari: ${f.search}` } : null,
        f.brand ? { key: 'brand', label: `Brand: ${brands[f.brand].label}` } : null,
        f.payment_status ? { key: 'payment_status', label: `Pembayaran: ${paymentStatuses[f.payment_status].label}` } : null,
        f.order_status ? { key: 'order_status', label: `Pesanan: ${orderStatuses[f.order_status].label}` } : null,
        f.payment_method ? { key: 'payment_method', label: `Metode: ${f.payment_method}` } : null,
        f.package ? { key: 'package', label: `Paket: ${packageLabel(f.package)}` } : null,
        f.theme_code ? { key: 'theme_code', label: `Tema: ${f.theme_code}` } : null,
        f.event_type ? { key: 'event_type', label: `Jenis acara: ${f.event_type}` } : null,
        f.event_date ? { key: 'event_date', label: `Tanggal acara: ${f.event_date}` } : null,
        f.date_from ? { key: 'date_from', label: `Dari: ${f.date_from}` } : null,
        f.date_to ? { key: 'date_to', label: `Sampai: ${f.date_to}` } : null,
    ].filter((filter): filter is ActiveFilter => filter !== null);
});

function clearAll(): void {
    search.value = '';
    emit('change', {
        search: null,
        brand: null,
        payment_status: null,
        order_status: null,
        payment_method: null,
        package: null,
        theme_code: null,
        event_type: null,
        event_date: null,
        date_from: null,
        date_to: null,
        page: null,
    });
}

const set = (key: keyof TransactionFilters, value: string | null): void => emit('change', { [key]: value, page: null });
const asBrand = (value: Brand | null): string | null => value;
</script>

<template>
    <FilterBar :active="active" @remove="set($event as keyof TransactionFilters, null)" @clear="clearAll">
        <div class="w-full md:w-72">
            <span aria-hidden="true" class="mb-1.5 block text-xs font-semibold text-muted-foreground">Cari</span>
            <SearchInput id="transaction-search" v-model="search" label="Cari invoice atau pelanggan" />
        </div>
        <SelectFilter
            id="filter-brand"
            label="Brand"
            :options="brandOptions"
            :model-value="asBrand(filters.brand)"
            @update:model-value="set('brand', $event)"
        />
        <SelectFilter
            id="filter-payment"
            label="Pembayaran"
            :options="paymentOptions"
            :model-value="filters.payment_status"
            @update:model-value="set('payment_status', $event)"
        />
        <SelectFilter
            id="filter-order"
            label="Pesanan"
            :options="orderOptions"
            :model-value="filters.order_status"
            @update:model-value="set('order_status', $event)"
        />
        <SelectFilter
            v-if="methodOptions.length"
            id="filter-method"
            label="Metode"
            :options="methodOptions"
            :model-value="filters.payment_method"
            @update:model-value="set('payment_method', $event)"
        />
        <SelectFilter id="filter-package" label="Paket" :options="packageOptions" :model-value="filters.package" @update:model-value="set('package', $event)" />
        <template v-if="showInvitationFilters">
            <SelectFilter
                v-if="themeOptions.length"
                id="filter-theme"
                label="Tema"
                :options="themeOptions"
                :model-value="filters.theme_code"
                @update:model-value="set('theme_code', $event)"
            />
            <SelectFilter
                v-if="eventTypeOptions.length"
                id="filter-event-type"
                label="Jenis acara"
                :options="eventTypeOptions"
                :model-value="filters.event_type"
                @update:model-value="set('event_type', $event)"
            />
            <div class="flex flex-1 flex-col gap-1.5 sm:flex-none">
                <label for="filter-event-date" class="text-xs font-semibold text-muted-foreground">Tanggal acara</label>
                <Input
                    id="filter-event-date"
                    type="date"
                    class="h-11 bg-card"
                    :model-value="filters.event_date ?? ''"
                    @change="set('event_date', ($event.target as HTMLInputElement).value || null)"
                />
            </div>
        </template>
        <DateRangeFilter :from="filters.date_from" :to="filters.date_to" id-prefix="transaction-date" @change="emit('change', { ...$event, page: null })" />
    </FilterBar>
</template>
