<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { contentFields, hashtagCount } from '@/lib/content';
import { copy } from '@/lib/copy';
import { formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ContentItem, ContentLimits, ContentRevision } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { Sparkles, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import ContentImageField from './ContentImageField.vue';

/**
 * The post's content. Mount it keyed by revision id so a save that creates a revision starts from the server's values.
 * When editing is unavailable the fields are read-only and the reason is shown.
 */
const props = defineProps<{ item: ContentItem; revision: ContentRevision; blockedReason: string | null; limits: ContentLimits }>();

const form = useForm({
    revision: props.revision.id,
    objective: props.revision.objective ?? '',
    audience: props.revision.audience ?? '',
    brief: props.revision.brief ?? '',
    caption: props.revision.caption ?? '',
    hashtags: props.revision.hashtags ?? '',
    alt_text: props.revision.altText ?? '',
    visual_direction: props.revision.visualDirection ?? '',
    image: null as File | null,
});

type FormKey = 'objective' | 'audience' | 'brief' | 'caption' | 'hashtags' | 'alt_text' | 'visual_direction';
const fields = contentFields.map((field) => ({ ...field, name: field.name as FormKey }));
const readonly = computed(() => props.blockedReason !== null);
const tags = computed(() => hashtagCount(form.hashtags));

function counter(name: FormKey, max: number): string {
    if (name === 'hashtags') return `${tags.value}/${props.limits.maxHashtags} hashtag`;

    return `${formatNumber(form[name].length)}/${formatNumber(max)}`;
}

function save(): void {
    form.transform((data) => ({ ...data, _method: 'put', image: data.image ?? undefined })).post(routes.contentItem(props.item.id), {
        preserveScroll: true,
        forceFormData: true,
        // The saved values become the baseline; the uploaded file is now the stored image.
        onSuccess: () => {
            form.image = null;
            form.revision = props.revision.id;
            form.defaults();
        },
    });
}
</script>

<template>
    <form class="space-y-5" novalidate @submit.prevent="save">
        <p v-if="blockedReason" class="flex items-start gap-2 rounded-lg bg-neutral-surface p-3 text-sm text-neutral" data-testid="edit-blocked">
            <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{{ blockedReason }}
        </p>
        <p
            v-if="!blockedReason && revision.generatedBy"
            class="flex items-start gap-2 rounded-lg bg-info-surface p-3 text-sm text-info"
            data-testid="ai-draft-note"
        >
            <Sparkles class="mt-0.5 size-4 shrink-0" aria-hidden="true" />Draft ini ditulis AI ({{
                revision.generatedBy.model ?? revision.generatedBy.provider
            }}). Periksa fakta, gaya bahasa, dan temuan aturan brand sebelum mengajukan.
        </p>
        <p v-else-if="item.status === 'APPROVED'" class="flex items-start gap-2 rounded-lg bg-warning-surface p-3 text-sm text-warning">
            <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />Konten ini sudah disetujui. Menyimpan perubahan membuat revisi baru, membatalkan
            persetujuan untuk posting, dan mengembalikannya ke tinjauan.
        </p>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="space-y-4">
                <FormField
                    v-for="field in fields"
                    :id="`content-${field.name}`"
                    :key="field.name"
                    v-slot="{ describedBy, invalid }"
                    :label="field.required ? `${field.label} *` : field.label"
                    :error="form.errors[field.name]"
                    :hint="field.hint"
                >
                    <Textarea
                        :id="`content-${field.name}`"
                        v-model="form[field.name]"
                        :rows="field.rows"
                        :maxlength="field.max"
                        :readonly="readonly"
                        :aria-describedby="`${describedBy ?? ''} content-${field.name}-count`.trim()"
                        :aria-invalid="invalid"
                        :class="readonly ? 'bg-muted' : ''"
                    />
                    <p :id="`content-${field.name}-count`" class="text-right text-xs text-muted-foreground tabular">{{ counter(field.name, field.max) }}</p>
                </FormField>
            </div>
            <ContentImageField v-model="form.image" :asset="revision.asset" :disabled="readonly" :error="form.errors.image" :alt="revision.altText" />
        </div>

        <div v-if="!readonly" class="flex flex-wrap items-center justify-end gap-3 border-t pt-4">
            <p v-if="form.isDirty" class="text-xs text-muted-foreground">Ada perubahan yang belum disimpan.</p>
            <Button type="submit" :disabled="form.processing" :aria-busy="form.processing">{{ form.processing ? copy.saving : 'Simpan konten' }}</Button>
        </div>
    </form>
</template>
