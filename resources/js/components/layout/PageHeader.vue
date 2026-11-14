<script setup lang="ts">
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div class="min-w-0">
        <h1 class="line-clamp-2 text-2xl font-bold break-words lg:text-[32px] lg:leading-tight">{{ title }}</h1>
        <p v-if="description" class="truncate text-sm text-muted-foreground">{{ description }}</p>
    </div>
</template>
