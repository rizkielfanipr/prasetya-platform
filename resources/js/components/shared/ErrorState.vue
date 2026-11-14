<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { copy } from '@/lib/copy';
import { TriangleAlert } from '@lucide/vue';

withDefaults(defineProps<{ title?: string; description?: string; retryable?: boolean }>(), {
    title: copy.errorTitle,
    description: copy.errorBody,
    retryable: false,
});
defineEmits<{ retry: [] }>();
</script>

<template>
    <div role="alert" class="flex flex-col items-center justify-center gap-2 px-6 py-12 text-center">
        <TriangleAlert class="size-8 text-danger" aria-hidden="true" />
        <p class="text-base font-semibold">{{ title }}</p>
        <p class="max-w-md text-muted-foreground">{{ description }}</p>
        <Button v-if="retryable" variant="outline" class="mt-2 h-11" @click="$emit('retry')">{{ copy.retry }}</Button>
    </div>
</template>
