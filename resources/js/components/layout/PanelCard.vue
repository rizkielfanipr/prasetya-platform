<script setup lang="ts">
defineProps<{ title?: string; description?: string; flush?: boolean }>();
</script>

<template>
    <section class="overflow-hidden rounded-xl border bg-card">
        <header v-if="title || $slots.actions" class="flex flex-wrap items-center justify-between gap-3 px-5 pt-5 pb-3">
            <div>
                <h2 v-if="title" class="text-xl font-bold">{{ title }}</h2>
                <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
            </div>
            <slot name="actions" />
        </header>
        <div :class="flush ? '' : 'px-5 pb-5'"><slot /></div>
    </section>
</template>
