<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Textarea } from '@/components/ui/textarea';
import { contentField, hashtagCount } from '@/lib/content';
import { formatNumber } from '@/lib/format';
import type { ContentLimits } from '@/types/content';
import { computed } from 'vue';

/**
 * Caption Instagram (ADR-0013): the caption and, right under it, its hashtags. They read as one post but stay two
 * fields with their own limits, errors, and focus targets, because the server validates hashtags apart.
 */
const props = defineProps<{ limits: ContentLimits; readonly: boolean; errors: { caption?: string; hashtags?: string } }>();
const caption = defineModel<string>('caption', { required: true });
const hashtags = defineModel<string>('hashtags', { required: true });

const captionField = contentField('caption');
const hashtagField = contentField('hashtags');
const tags = computed(() => hashtagCount(hashtags.value));
</script>

<template>
    <section aria-labelledby="compose-title" class="space-y-4" data-testid="caption-area">
        <h2 id="compose-title" class="text-base font-bold">Caption Instagram</h2>
        <FormField id="content-caption" label="Caption *" :error="errors.caption" :hint="captionField?.hint">
            <template #actions>
                <span id="content-caption-count" class="text-xs text-muted-foreground tabular"
                    >{{ formatNumber(caption.length) }}/{{ formatNumber(limits.captionMax) }}</span
                >
            </template>
            <template #default="{ describedBy, invalid }">
                <Textarea
                    id="content-caption"
                    v-model="caption"
                    :rows="captionField?.rows"
                    :maxlength="limits.captionMax"
                    :readonly="readonly"
                    :aria-describedby="`${describedBy ?? ''} content-caption-count`.trim()"
                    :aria-invalid="invalid"
                    :class="readonly ? 'bg-muted' : ''"
                />
            </template>
        </FormField>
        <FormField id="content-hashtags" label="Hashtag" :error="errors.hashtags" :hint="hashtagField?.hint">
            <template #actions>
                <span id="content-hashtags-count" class="text-xs text-muted-foreground tabular">{{ tags }}/{{ props.limits.maxHashtags }} hashtag</span>
            </template>
            <template #default="{ describedBy, invalid }">
                <Textarea
                    id="content-hashtags"
                    v-model="hashtags"
                    :rows="hashtagField?.rows"
                    :maxlength="hashtagField?.max"
                    :readonly="readonly"
                    :aria-describedby="`${describedBy ?? ''} content-hashtags-count`.trim()"
                    :aria-invalid="invalid"
                    :class="readonly ? 'bg-muted' : ''"
                />
            </template>
        </FormField>
    </section>
</template>
