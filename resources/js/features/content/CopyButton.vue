<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

/** Copies text and says so in a polite live region, so screen-reader users hear the result too. */
const props = defineProps<{ text: string; label: string; done: string }>();
const copied = ref(false);
const announcement = ref('');

async function copyText(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.text);
        copied.value = true;
        announcement.value = props.done;
        window.setTimeout(() => {
            copied.value = false;
            announcement.value = '';
        }, 2500);
    } catch {
        toast.error('Teks tidak dapat disalin. Pilih teksnya lalu salin manual.');
    }
}
</script>

<template>
    <Button type="button" variant="outline" size="sm" class="gap-1.5" :disabled="text === ''" @click="copyText">
        <Check v-if="copied" class="size-4" aria-hidden="true" /><Copy v-else class="size-4" aria-hidden="true" />{{ copied ? 'Disalin' : label }}
    </Button>
    <span class="sr-only" aria-live="polite">{{ announcement }}</span>
</template>
