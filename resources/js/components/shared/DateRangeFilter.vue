<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { copy } from '@/lib/copy';

defineProps<{ from: string | null; to: string | null; idPrefix?: string }>();
const emit = defineEmits<{ change: [range: { date_from: string | null; date_to: string | null }] }>();
</script>

<template>
    <fieldset class="flex flex-wrap gap-3">
        <legend class="sr-only">Rentang tanggal</legend>
        <div class="flex flex-1 flex-col gap-1.5 sm:flex-none">
            <label :for="`${idPrefix ?? 'date'}-from`" class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">{{
                copy.dateFrom
            }}</label>
            <Input
                :id="`${idPrefix ?? 'date'}-from`"
                type="date"
                class="h-11 bg-card"
                :model-value="from ?? ''"
                :max="to ?? undefined"
                @change="emit('change', { date_from: ($event.target as HTMLInputElement).value || null, date_to: to })"
            />
        </div>
        <div class="flex flex-1 flex-col gap-1.5 sm:flex-none">
            <label :for="`${idPrefix ?? 'date'}-to`" class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">{{
                copy.dateTo
            }}</label>
            <Input
                :id="`${idPrefix ?? 'date'}-to`"
                type="date"
                class="h-11 bg-card"
                :model-value="to ?? ''"
                :min="from ?? undefined"
                @change="emit('change', { date_from: from, date_to: ($event.target as HTMLInputElement).value || null })"
            />
        </div>
    </fieldset>
</template>
