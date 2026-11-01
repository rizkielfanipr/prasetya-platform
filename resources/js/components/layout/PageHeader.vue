<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="min-w-0">
        <h1 class="truncate text-2xl font-bold lg:text-[32px] lg:leading-tight">{{ title }}</h1>
        <p v-if="description" class="truncate text-sm text-muted-foreground">{{ description }}</p>
    </div>
</template>
