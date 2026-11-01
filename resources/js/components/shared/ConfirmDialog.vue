<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { copy } from '@/lib/copy';

withDefaults(defineProps<{ title: string; description: string; confirmLabel: string; submitting?: boolean; destructive?: boolean }>(), {
    submitting: false,
    destructive: false,
});
const open = defineModel<boolean>('open', { default: false });
defineEmits<{ confirm: [] }>();
</script>

<template>
    <!-- Reka handles the focus trap and returns focus to the trigger on close. -->
    <AlertDialog v-model:open="open">
        <AlertDialogTrigger v-if="$slots.trigger" as-child><slot name="trigger" /></AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>{{ title }}</AlertDialogTitle>
                <AlertDialogDescription>{{ description }}</AlertDialogDescription>
            </AlertDialogHeader>
            <slot />
            <AlertDialogFooter>
                <AlertDialogCancel :disabled="submitting">{{ copy.cancel }}</AlertDialogCancel>
                <Button :variant="destructive ? 'destructive' : 'default'" :disabled="submitting" :aria-busy="submitting" @click="$emit('confirm')">
                    {{ submitting ? copy.saving : confirmLabel }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
