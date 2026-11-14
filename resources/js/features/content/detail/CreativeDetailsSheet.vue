<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { contentFields } from '@/lib/content';
import { copy } from '@/lib/copy';
import { formatNumber } from '@/lib/format';
import DetailSheet from './DetailSheet.vue';

/**
 * Detail kreatif (ADR-0013, Draft only): objective, audience, and visual direction. Ready does not need them, so they
 * leave the main flow, but they are part of the same revision: Simpan saves the whole Draft form, and what is typed
 * here stays in that form (marked unsaved) when the sheet closes without saving.
 */
defineProps<{ readonly: boolean; saving: boolean; errors: { objective?: string; audience?: string; visual_direction?: string } }>();
const open = defineModel<boolean>('open', { default: false });
const objective = defineModel<string>('objective', { required: true });
const audience = defineModel<string>('audience', { required: true });
const visualDirection = defineModel<string>('visualDirection', { required: true });
defineEmits<{ save: [] }>();

const fields = contentFields.filter((field) => field.group === 'creative');
const models = { objective, audience, visual_direction: visualDirection } as const;
type CreativeName = keyof typeof models;
const isCreative = (name: string): name is CreativeName => name in models;
</script>

<template>
    <DetailSheet
        v-model:open="open"
        title="Detail kreatif"
        description="Tujuan, audiens, dan arahan visual. Tidak wajib untuk siap posting; AI memakainya sebagai konteks."
        testid="creative-sheet"
    >
        <div class="space-y-5">
            <template v-for="field in fields" :key="field.name">
                <FormField v-if="isCreative(field.name)" :id="`content-${field.name}`" :label="field.label" :error="errors[field.name]" :hint="field.hint">
                    <template #actions>
                        <span :id="`content-${field.name}-count`" class="text-xs text-muted-foreground tabular"
                            >{{ formatNumber(models[field.name].value.length) }}/{{ formatNumber(field.max) }}</span
                        >
                    </template>
                    <template #default="{ describedBy, invalid }">
                        <Textarea
                            :id="`content-${field.name}`"
                            v-model="models[field.name].value"
                            :rows="field.rows"
                            :maxlength="field.max"
                            :readonly="readonly"
                            :aria-describedby="`${describedBy ?? ''} content-${field.name}-count`.trim()"
                            :aria-invalid="invalid"
                            :class="readonly ? 'bg-muted' : ''"
                        />
                    </template>
                </FormField>
            </template>
        </div>
        <template v-if="!readonly" #footer>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <p class="text-xs text-muted-foreground">Menyimpan semua perubahan Draft, termasuk caption dan media.</p>
                <Button class="h-11" :disabled="saving" :aria-busy="saving" @click="$emit('save')">{{ saving ? copy.saving : 'Simpan detail' }}</Button>
            </div>
        </template>
    </DetailSheet>
</template>
