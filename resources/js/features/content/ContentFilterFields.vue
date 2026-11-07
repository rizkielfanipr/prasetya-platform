<script setup lang="ts">
import SelectFilter from '@/components/shared/SelectFilter.vue';
import { contentStatuses, contentStatusOrder } from '@/lib/content';
import type { ContentBrand, ContentFilters } from '@/types/content';
import { computed } from 'vue';

const props = defineProps<{ filters: ContentFilters; brands: ContentBrand[] }>();
defineEmits<{ apply: [changes: Partial<Record<keyof ContentFilters, string | null>>] }>();

const statusOptions = contentStatusOrder.map((value) => ({ value, label: contentStatuses[value].label }));
const brandOptions = computed(() => props.brands.map((brand) => ({ value: brand.code, label: brand.name })));
</script>

<template>
    <SelectFilter
        v-if="brands.length > 1"
        id="filter-content-brand"
        label="Brand"
        all-label="Semua brand"
        :options="brandOptions"
        :model-value="filters.brand"
        @update:model-value="$emit('apply', { brand: $event })"
    />
    <SelectFilter
        id="filter-content-status"
        label="Status"
        all-label="Semua status"
        :options="statusOptions"
        :model-value="filters.status"
        @update:model-value="$emit('apply', { status: $event })"
    />
</template>
