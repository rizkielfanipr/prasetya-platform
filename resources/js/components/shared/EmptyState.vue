<script setup lang="ts">
import { copy } from '@/lib/copy';
import { Inbox, type LucideIcon } from '@lucide/vue';
import { computed } from 'vue';

const props = withDefaults(defineProps<{ title?: string; description?: string; icon?: LucideIcon }>(), {
    title: copy.emptyTitle,
    description: undefined,
    icon: undefined,
});
// Not a prop default: Vue would call a component function as a default factory.
const resolvedIcon = computed(() => props.icon ?? Inbox);
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
        <component :is="resolvedIcon" class="size-8 text-muted-foreground" aria-hidden="true" />
        <p class="text-base font-semibold">{{ title }}</p>
        <p v-if="description" class="max-w-md text-muted-foreground">{{ description }}</p>
        <div v-if="$slots.default" class="mt-2"><slot /></div>
    </div>
</template>
