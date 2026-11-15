<script setup lang="ts">
/**
 * A region of a migrated page (DESIGN.md, Flat Board Rule): a hairline top rule and a 14 px / 800 heading instead of a
 * bordered card. Same props and slots as PanelCard, so a screen moves over by swapping the component.
 */
defineProps<{ title?: string; description?: string; flush?: boolean }>();
</script>

<template>
    <section class="min-w-0 border-t border-border pt-4">
        <header v-if="title || $slots.actions" class="mb-3 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 v-if="title" class="text-sm font-extrabold">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 max-w-[70ch] text-[13px] text-muted-foreground">{{ description }}</p>
            </div>
            <slot name="actions" />
        </header>
        <slot />
    </section>
</template>
