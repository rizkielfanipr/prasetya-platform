<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { copy } from '@/lib/copy';
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

withDefaults(defineProps<{ label?: string; placeholder?: string; id?: string }>(), { label: copy.search, placeholder: copy.searchPlaceholder, id: 'search' });
const model = defineModel<string>({ default: '' });
</script>

<template>
    <div class="relative w-full">
        <label :for="id" class="sr-only">{{ label }}</label>
        <MagnifyingGlassIcon class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 stroke-2 text-muted-foreground" aria-hidden="true" />
        <Input :id="id" v-model="model" type="search" :placeholder="placeholder" class="h-11 bg-card pl-9" autocomplete="off" />
    </div>
</template>
