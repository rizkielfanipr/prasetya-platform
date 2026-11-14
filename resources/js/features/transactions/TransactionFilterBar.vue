<script setup lang="ts">
import DateRangeFilter from '@/components/shared/DateRangeFilter.vue';
import FilterBar, { type ActiveFilter } from '@/components/shared/FilterBar.vue';
import SearchInput from '@/components/shared/SearchInput.vue';
import SelectFilter from '@/components/shared/SelectFilter.vue';
import { Input } from '@/components/ui/input';
import { brandCodes, brands } from '@/lib/brands';
import { paymentMethodLabel } from '@/lib/payment-methods';
import { orderStatuses, paymentStatuses } from '@/lib/status';
import type { Brand, OrderStatus, PaymentStatus, TransactionFilterOptions, TransactionFilters } from '@/types/backoffice';
import { AdjustmentsHorizontalIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
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
const methodOptions = computed(() => props.options.paymentMethods.map((value) => ({ value, label: paymentMethodLabel(value) ?? value })));
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
        f.payment_method ? { key: 'payment_method', label: `Metode: ${paymentMethodLabel(f.payment_method)}` } : null,
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

// Search, brand, and the two statuses answer the daily questions; the rest wait behind "Filter Lain".
const secondaryKeys = ['payment_method', 'package', 'theme_code', 'event_type', 'event_date', 'date_from', 'date_to'] as const;
const secondaryActive = computed(() => secondaryKeys.filter((key) => props.filters[key]).length);
// Opens on its own when a secondary filter is already set, so an applied filter is never hidden.
const moreOpen = ref(secondaryActive.value > 0);
// Below md the three daily selects also fold in here, so rows reach the first phone screen; the count follows.
const primaryActive = computed(() => (['brand', 'payment_status', 'order_status'] as const).filter((key) => props.filters[key]).length);
const foldedOnPhone = computed(() => (moreOpen.value ? '' : 'max-md:hidden'));
</script>

<template>
    <FilterBar :active="active" @remove="set($event as keyof TransactionFilters, null)" @clear="clearAll">
        <div class="w-full min-w-56 lg:flex-1">
            <span aria-hidden="true" class="mb-1.5 block text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Cari</span>
            <SearchInput id="transaction-search" v-model="search" label="Cari invoice atau pelanggan" />
        </div>
        <SelectFilter
            id="filter-brand"
            :class="foldedOnPhone"
            label="Brand"
            :options="brandOptions"
            :model-value="asBrand(filters.brand)"
            @update:model-value="set('brand', $event)"
        />
        <SelectFilter
            id="filter-payment"
            :class="foldedOnPhone"
            label="Pembayaran"
            :options="paymentOptions"
            :model-value="filters.payment_status"
            @update:model-value="set('payment_status', $event)"
        />
        <SelectFilter
            id="filter-order"
            :class="foldedOnPhone"
            label="Pesanan"
            :options="orderOptions"
            :model-value="filters.order_status"
            @update:model-value="set('order_status', $event)"
        />
        <button
            type="button"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md border border-input bg-card px-4 text-sm font-bold transition-colors hover:bg-tint-100 motion-reduce:transition-none sm:w-auto"
            :aria-expanded="moreOpen"
            aria-controls="transaction-more-filters"
            data-testid="more-filters"
            @click="moreOpen = !moreOpen"
        >
            <AdjustmentsHorizontalIcon class="size-4 stroke-2" aria-hidden="true" />
            Filter Lain
            <span v-if="secondaryActive" class="rounded-md bg-tint-200 px-1.5 text-xs leading-5 font-extrabold text-tint-800 tabular max-md:hidden">{{
                secondaryActive
            }}</span>
            <span
                v-if="secondaryActive + primaryActive"
                class="rounded-md bg-tint-200 px-1.5 text-xs leading-5 font-extrabold text-tint-800 tabular md:hidden"
                >{{ secondaryActive + primaryActive }}</span
            >
            <ChevronDownIcon
                class="size-4 stroke-2 transition-transform motion-reduce:transition-none"
                :class="{ 'rotate-180': moreOpen }"
                aria-hidden="true"
            />
        </button>
        <div v-show="moreOpen" id="transaction-more-filters" class="flex w-full flex-wrap items-end gap-3 border-t border-tint-200 pt-3">
            <SelectFilter
                v-if="methodOptions.length"
                id="filter-method"
                label="Metode"
                :options="methodOptions"
                :model-value="filters.payment_method"
                @update:model-value="set('payment_method', $event)"
            />
            <SelectFilter
                id="filter-package"
                label="Paket"
                :options="packageOptions"
                :model-value="filters.package"
                @update:model-value="set('package', $event)"
            />
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
                    <label for="filter-event-date" class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Tanggal acara</label>
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
        </div>
    </FilterBar>
</template>
