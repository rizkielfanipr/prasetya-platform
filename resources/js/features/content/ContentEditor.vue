<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { contentFields, hashtagCount } from '@/lib/content';
import { copy } from '@/lib/copy';
import { formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ContentActions, ContentItem, ContentLimits, ContentRevision, ItemAi, RuleFinding } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { ChevronDown, Sparkles, TriangleAlert } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import ContentAiAction from './ContentAiAction.vue';
import ContentImageField from './ContentImageField.vue';
import ContentRuleFindings from './ContentRuleFindings.vue';
import ContentStatusPanel from './ContentStatusPanel.vue';

/**
 * The post editor (ADR-0012, phase 3), in working order: brief, Buat dengan AI, caption with hashtags, image, brand
 * findings, then Simpan and Siap posting. Alt text, objective, audience, visual direction, and whatever the page puts
 * in the `more` slot sit under Pengaturan tambahan, which opens by itself and moves focus when one of its fields has an
 * error. Fields stay separate on the server (hashtags are validated apart from the caption).
 */
const props = defineProps<{
    item: ContentItem;
    revision: ContentRevision;
    blockedReason: string | null;
    limits: ContentLimits;
    findings: RuleFinding[];
    brandRulesVersion: number | null;
    ai: ItemAi;
    actions: ContentActions;
}>();

type FormKey = 'objective' | 'audience' | 'brief' | 'caption' | 'hashtags' | 'alt_text' | 'visual_direction';
const valuesOf = (revision: ContentRevision) => ({
    revision: revision.id,
    objective: revision.objective ?? '',
    audience: revision.audience ?? '',
    brief: revision.brief ?? '',
    caption: revision.caption ?? '',
    hashtags: revision.hashtags ?? '',
    alt_text: revision.altText ?? '',
    visual_direction: revision.visualDirection ?? '',
});
const form = useForm({ ...valuesOf(props.revision), image: null as File | null });

const field = (name: FormKey) => {
    const found = contentFields.find((f) => f.name === name);
    if (!found) throw new Error(`Unknown content field ${name}`);
    return { ...found, name };
};
const brief = field('brief');
const caption = field('caption');
const hashtags = field('hashtags');
const advanced = contentFields.filter((f) => f.group === 'advanced').map((f) => field(f.name as FormKey));
const order: (FormKey | 'image')[] = ['brief', 'caption', 'hashtags', 'image', ...advanced.map((f) => f.name)];

const aiActive = computed(() => props.ai.runs.some((run) => run.status === 'QUEUED' || run.status === 'RUNNING'));
const readonly = computed(() => props.blockedReason !== null || aiActive.value);
const tags = computed(() => hashtagCount(form.hashtags));
// The last thing a complete draft lacks is often alt text: show it rather than hide it behind the disclosure.
const moreOpen = ref(props.item.status === 'DRAFT' && !!props.revision.caption && props.revision.asset !== null && !props.revision.altText);

function counter(name: FormKey, max: number): string {
    if (name === 'hashtags') return `${tags.value}/${props.limits.maxHashtags} hashtag`;

    return `${formatNumber(form[name].length)}/${formatNumber(max)}`;
}

// A new revision (saved from Ready, or written by AI) becomes the baseline.
watch(
    () => props.revision.id,
    () => {
        Object.assign(form, valuesOf(props.revision), { image: null });
        form.defaults();
        form.clearErrors();
    },
);

async function focusFirstError(): Promise<void> {
    const errors = form.errors as Partial<Record<FormKey | 'image', string>>;
    const first = order.find((name) => errors[name]);
    if (!first) return;
    if (advanced.some((f) => f.name === first)) moreOpen.value = true;
    await nextTick();
    document.getElementById(first === 'image' ? 'content-image' : `content-${first}`)?.focus();
}

/** Saves the content; true when the server accepted it. */
function save(): Promise<boolean> {
    return new Promise((resolve) => {
        form.transform((data) => ({ ...data, _method: 'put', image: data.image ?? undefined })).post(routes.contentItem(props.item.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.image = null;
                form.revision = props.revision.id;
                form.defaults();
                resolve(true);
            },
            onError: () => {
                void focusFirstError();
                resolve(false);
            },
        });
    });
}

/** Before AI drafts: save unsaved edits so AI reads what the person sees, and answer the revision to draft from. */
async function prepareForAi(): Promise<string | null> {
    if (!form.isDirty) return props.revision.id;

    return (await save()) ? props.revision.id : null;
}
</script>

<template>
    <div class="space-y-5" data-testid="content-editor">
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
            }}). Periksa fakta, gaya bahasa, dan temuan aturan brand sebelum menandai siap posting.
        </p>
        <p v-else-if="item.status === 'READY'" class="flex items-start gap-2 rounded-lg bg-warning-surface p-3 text-sm text-warning">
            <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />Konten ini sudah siap posting. Menyimpan perubahan membuat revisi baru dan
            mengembalikannya ke Draft.
        </p>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="min-w-0 space-y-4">
                <FormField :id="`content-${brief.name}`" v-slot="{ describedBy, invalid }" :label="brief.label" :error="form.errors.brief" :hint="brief.hint">
                    <Textarea
                        :id="`content-${brief.name}`"
                        v-model="form.brief"
                        :rows="brief.rows"
                        :maxlength="brief.max"
                        :readonly="readonly"
                        :aria-describedby="`${describedBy ?? ''} content-brief-count`.trim()"
                        :aria-invalid="invalid"
                        :class="readonly ? 'bg-muted' : ''"
                    />
                    <p id="content-brief-count" class="text-right text-xs text-muted-foreground tabular">{{ counter('brief', brief.max) }}</p>
                </FormField>

                <ContentAiAction :item="item" :revision="revision" :ai="ai" :prepare="prepareForAi" />

                <fieldset class="space-y-3 rounded-lg border bg-card p-3" data-testid="caption-area">
                    <legend class="px-1 text-sm font-semibold">Caption dan hashtag</legend>
                    <FormField
                        v-for="part in [caption, hashtags]"
                        :id="`content-${part.name}`"
                        :key="part.name"
                        v-slot="{ describedBy, invalid }"
                        :label="part.required ? `${part.label} *` : part.label"
                        :error="form.errors[part.name]"
                        :hint="part.hint"
                    >
                        <Textarea
                            :id="`content-${part.name}`"
                            v-model="form[part.name]"
                            :rows="part.rows"
                            :maxlength="part.max"
                            :readonly="readonly"
                            :aria-describedby="`${describedBy ?? ''} content-${part.name}-count`.trim()"
                            :aria-invalid="invalid"
                            :class="readonly ? 'bg-muted' : ''"
                        />
                        <p :id="`content-${part.name}-count`" class="text-right text-xs text-muted-foreground tabular">{{ counter(part.name, part.max) }}</p>
                    </FormField>
                </fieldset>
            </div>
            <ContentImageField v-model="form.image" :asset="revision.asset" :disabled="readonly" :error="form.errors.image" :alt="revision.altText" />
        </div>

        <section aria-labelledby="content-findings-title" class="space-y-2">
            <h3 id="content-findings-title" class="text-sm font-semibold">Aturan brand</h3>
            <ContentRuleFindings :findings="findings" :version="brandRulesVersion" :submitted-version="revision.brandRulesVersion" />
        </section>

        <div v-if="!readonly" class="flex flex-wrap items-center justify-end gap-3 border-t pt-4">
            <p v-if="form.isDirty" class="text-xs text-muted-foreground" role="status">Ada perubahan yang belum disimpan.</p>
            <Button type="button" :disabled="form.processing" :aria-busy="form.processing" @click="save">{{
                form.processing ? copy.saving : 'Simpan konten'
            }}</Button>
        </div>

        <section aria-labelledby="content-status-title" class="space-y-2 rounded-lg bg-muted/40 p-4">
            <h3 id="content-status-title" class="text-sm font-semibold">Siap posting</h3>
            <ContentStatusPanel :item="item" :revision="revision" :actions="actions" :dirty="form.isDirty" />
        </section>

        <div class="border-t pt-4">
            <Button
                type="button"
                variant="ghost"
                class="-ml-2 gap-1.5"
                aria-controls="content-more"
                :aria-expanded="moreOpen"
                data-testid="more-settings"
                @click="moreOpen = !moreOpen"
            >
                Pengaturan tambahan
                <ChevronDown class="size-4 transition-transform motion-reduce:transition-none" :class="{ 'rotate-180': moreOpen }" aria-hidden="true" />
            </Button>
            <p class="text-xs text-muted-foreground">Alt text, tujuan, audiens, arahan visual, rencana, riset, dan riwayat.</p>
            <div v-show="moreOpen" id="content-more" class="mt-4 space-y-6">
                <section aria-labelledby="content-details-title" class="space-y-4">
                    <h3 id="content-details-title" class="text-sm font-semibold">Detail konten</h3>
                    <FormField
                        v-for="part in advanced"
                        :id="`content-${part.name}`"
                        :key="part.name"
                        v-slot="{ describedBy, invalid }"
                        :label="part.required ? `${part.label} *` : part.label"
                        :error="form.errors[part.name]"
                        :hint="part.hint"
                    >
                        <Textarea
                            :id="`content-${part.name}`"
                            v-model="form[part.name]"
                            :rows="part.rows"
                            :maxlength="part.max"
                            :readonly="readonly"
                            :aria-describedby="`${describedBy ?? ''} content-${part.name}-count`.trim()"
                            :aria-invalid="invalid"
                            :class="readonly ? 'bg-muted' : ''"
                        />
                        <p :id="`content-${part.name}-count`" class="text-right text-xs text-muted-foreground tabular">{{ counter(part.name, part.max) }}</p>
                    </FormField>
                    <div v-if="!readonly" class="flex justify-end">
                        <Button type="button" variant="outline" :disabled="form.processing" @click="save">Simpan detail konten</Button>
                    </div>
                </section>
                <slot name="more" />
            </div>
        </div>
    </div>
</template>
