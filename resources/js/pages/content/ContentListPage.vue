<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import FilterBar, { type ActiveFilter } from '@/components/shared/FilterBar.vue';
import SearchInput from '@/components/shared/SearchInput.vue';
import TablePagination from '@/components/shared/TablePagination.vue';
import { useUrlFilters } from '@/composables/useUrlFilters';
import ContentCreateDialog from '@/features/content/ContentCreateDialog.vue';
import ContentFilterFields from '@/features/content/ContentFilterFields.vue';
import ContentNav from '@/features/content/ContentNav.vue';
import ContentTable from '@/features/content/ContentTable.vue';
import { contentStatuses } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { Paginated } from '@/types/backoffice';
import type { ContentBrandOption, ContentListFilters, ContentRow } from '@/types/content';
import { computed, ref, watch } from 'vue';

const props = defineProps<{ filters: ContentListFilters; items: Paginated<ContentRow>; brands: ContentBrandOption[] }>();

const { apply, applyDebounced, loading, failed } = useUrlFilters(
    () => routes.contentItems,
    () => ({ ...props.filters }),
    ['filters', 'items'],
);

const search = ref(props.filters.search ?? '');
watch(search, (value) => {
    if (value !== (props.filters.search ?? '')) void applyDebounced({ search: value || null, page: null });
});

const brandName = (code: string) => props.brands.find((brand) => brand.code === code)?.name ?? code;
const pillarName = (id: string) => props.brands.flatMap((brand) => brand.pillars).find((pillar) => pillar.id === id)?.name ?? id;
const campaignName = (id: string) => props.brands.flatMap((brand) => brand.campaigns).find((campaign) => campaign.id === id)?.name ?? id;
const active = computed<ActiveFilter[]>(() =>
    [
        props.filters.search ? { key: 'search', label: `Cari: ${props.filters.search}` } : null,
        props.filters.brand ? { key: 'brand', label: `Brand: ${brandName(props.filters.brand)}` } : null,
        props.filters.pillar ? { key: 'pillar', label: `Pilar: ${pillarName(props.filters.pillar)}` } : null,
        props.filters.campaign ? { key: 'campaign', label: `Kampanye: ${campaignName(props.filters.campaign)}` } : null,
        props.filters.status ? { key: 'status', label: `Status: ${contentStatuses[props.filters.status].label}` } : null,
    ].filter((filter): filter is ActiveFilter => filter !== null),
);

function remove(key: string): void {
    if (key === 'search') search.value = '';
    apply({ [key]: null, page: null });
}

function clear(): void {
    search.value = '';
    apply({ search: null, brand: null, pillar: null, campaign: null, status: null, page: null });
}

function sortBy(key: string): void {
    const direction = props.filters.sort === key && props.filters.direction === 'desc' ? 'asc' : 'desc';
    apply({ sort: key, direction, page: null });
}
</script>

<template>
    <AppLayout title="Content Studio" description="Semua konten, dari draft sampai dipublikasikan.">
        <ContentNav current="items" />
        <FilterBar :active="active" @remove="remove" @clear="clear">
            <div class="w-full md:w-72">
                <span aria-hidden="true" class="mb-1.5 block text-xs font-semibold text-muted-foreground">Cari</span>
                <SearchInput id="content-search" v-model="search" label="Cari judul konten" placeholder="Cari judul" />
            </div>
            <ContentFilterFields :filters="filters" :brands="brands" @apply="apply({ ...$event, page: null })" />
            <div class="md:ml-auto"><ContentCreateDialog :brands="brands" /></div>
        </FilterBar>
        <PanelCard flush>
            <ContentTable
                :rows="items.data"
                :loading="loading"
                :failed="failed"
                :filtered="active.length > 0"
                :sort="filters.sort"
                :direction="filters.direction"
                @retry="apply({})"
                @sort="sortBy"
            />
            <TablePagination :meta="items.meta" :disabled="loading" @change="apply({ page: String($event) })" />
        </PanelCard>
    </AppLayout>
</template>
