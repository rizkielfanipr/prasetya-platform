<script setup lang="ts">
import { Label } from '@/components/ui/label';

defineProps<{ id: string; label: string; error?: string; hint?: string }>();
</script>

<template>
    <div class="space-y-2">
        <Label :for="id" class="text-sm font-semibold">{{ label }}</Label>
        <!-- aria-describedby ties the message to the input; role=alert announces it when it appears. -->
        <slot :described-by="error ? `${id}-error` : hint ? `${id}-hint` : undefined" :invalid="error ? true : undefined" />
        <p v-if="hint && !error" :id="`${id}-hint`" class="text-xs text-muted-foreground">{{ hint }}</p>
        <p v-if="error" :id="`${id}-error`" role="alert" class="text-xs font-medium text-danger">{{ error }}</p>
    </div>
</template>
