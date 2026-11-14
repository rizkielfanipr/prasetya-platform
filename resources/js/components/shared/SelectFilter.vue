<script setup lang="ts">
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { copy } from '@/lib/copy';
import { computed } from 'vue';

const props = defineProps<{ id: string; label: string; options: { value: string; label: string }[]; modelValue: string | null; allLabel?: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string | null] }>();

// Reka's Select does not allow an empty item value, so "all" needs a sentinel that never leaves this component.
const ALL = '__all__';
const value = computed({
    get: () => props.modelValue ?? ALL,
    set: (next: string) => emit('update:modelValue', next === ALL ? null : next),
});
</script>

<template>
    <div class="flex min-w-36 flex-1 flex-col gap-1.5 sm:flex-none">
        <label :id="`${id}-label`" class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">{{ label }}</label>
        <Select v-model="value">
            <SelectTrigger :id="id" :aria-labelledby="`${id}-label ${id}`" class="h-11 w-full bg-card font-semibold data-[size=default]:h-11">
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem :value="ALL">{{ allLabel ?? copy.all }}</SelectItem>
                <SelectItem v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</SelectItem>
            </SelectContent>
        </Select>
    </div>
</template>
