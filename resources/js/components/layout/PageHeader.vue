<script setup lang="ts">
/** The page's title and subtitle, set in the content (ADR-0014), never in the top header. */
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
        <div class="min-w-0">
            <h1 class="text-2xl leading-tight font-extrabold tracking-[-0.01em] break-words">{{ title }}</h1>
            <p v-if="description" class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 flex-wrap gap-2"><slot name="actions" /></div>
    </div>
</template>
