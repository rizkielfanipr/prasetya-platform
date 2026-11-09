<script setup lang="ts">
import SelectFilter from '@/components/shared/SelectFilter.vue';
import type { ContentBrandOption, ContentFilters } from '@/types/content';
import { computed } from 'vue';

/** Filter lainnya on the Kalender: brand, pillar, campaign, and the archive. The main status filter sits above them. */
const props = defineProps<{ filters: ContentFilters; brands: ContentBrandOption[] }>();
defineEmits<{ apply: [changes: Partial<Record<keyof ContentFilters, string | null>>] }>();

const brandOptions = computed(() => props.brands.map((brand) => ({ value: brand.code, label: brand.name })));
// Pillars and campaigns of the chosen brand, or of every brand when there is only one choice anyway.
const scoped = computed(() => props.brands.filter((brand) => !props.filters.brand || brand.code === props.filters.brand));
const pillarOptions = computed(() => scoped.value.flatMap((brand) => brand.pillars.map((pillar) => ({ value: pillar.id, label: pillar.name }))));
const campaignOptions = computed(() => scoped.value.flatMap((brand) => brand.campaigns.map((campaign) => ({ value: campaign.id, label: campaign.name }))));
const archiveOptions = [{ value: 'ARCHIVED', label: 'Hanya yang diarsipkan' }];
</script>

<template>
    <SelectFilter
        v-if="brands.length > 1"
        id="filter-content-brand"
        label="Brand"
        all-label="Semua brand"
        :options="brandOptions"
        :model-value="filters.brand"
        @update:model-value="$emit('apply', { brand: $event, pillar: null, campaign: null })"
    />
    <SelectFilter
        v-if="pillarOptions.length"
        id="filter-content-pillar"
        label="Pilar"
        all-label="Semua pilar"
        :options="pillarOptions"
        :model-value="filters.pillar"
        @update:model-value="$emit('apply', { pillar: $event })"
    />
    <SelectFilter
        v-if="campaignOptions.length"
        id="filter-content-campaign"
        label="Campaign"
        all-label="Semua campaign"
        :options="campaignOptions"
        :model-value="filters.campaign"
        @update:model-value="$emit('apply', { campaign: $event })"
    />
    <SelectFilter
        id="filter-content-archive"
        label="Arsip"
        all-label="Sembunyikan arsip"
        :options="archiveOptions"
        :model-value="filters.status === 'ARCHIVED' ? 'ARCHIVED' : null"
        @update:model-value="$emit('apply', { status: $event })"
    />
</template>
