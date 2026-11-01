<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import { copy } from '@/lib/copy';

withDefaults(defineProps<{ rows?: number; label?: string }>(), { rows: 4, label: copy.loading });
</script>

<template>
    <div role="status" class="space-y-3 p-5">
        <span class="sr-only">{{ label }}</span>
        <Skeleton v-for="row in rows" :key="row" class="h-9 w-full rounded-md" />
    </div>
</template>
