<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Textarea } from '@/components/ui/textarea';
import { contentField } from '@/lib/content';
import { formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ContentActions, ContentItem, ContentLimits, ContentRevision, ItemAi, RuleFinding } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { Sparkles, TriangleAlert } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import BrandCheckSummary from './BrandCheckSummary.vue';
import ContentAiAssistant from './ContentAiAssistant.vue';
import ContentMediaEditor from './ContentMediaEditor.vue';
import ContentReadinessPanel from './ContentReadinessPanel.vue';
import CreativeDetailsSheet from './CreativeDetailsSheet.vue';
import InstagramCompositionEditor from './InstagramCompositionEditor.vue';

/**
 * The Draft workspace (ADR-0013): one visible flow from brief to Ready, with no secondary surface needed. It owns the
 * revision form; the parts below edit it through v-model and the readiness rail presents the server's answer. It also
 * serves "Edit konten" on Ready content, where saving makes a new revision and returns the item to Draft.
 */
const props = defineProps<{
    item: ContentItem;
    revision: ContentRevision;
    actions: ContentActions;
    limits: ContentLimits;
    findings: RuleFinding[];
    brandRulesVersion: number | null;
    /** Null outside Draft: AI only writes Drafts. */
    ai: ItemAi | null;
    editingReady: boolean;
}>();
const creativeOpen = defineModel<boolean>('creativeOpen', { default: false });
const emit = defineEmits<{ cancelEdit: [] }>();

type FieldName = 'objective' | 'audience' | 'brief' | 'caption' | 'hashtags' | 'alt_text' | 'visual_direction';
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

const briefField = contentField('brief');
const order: (FieldName | 'image')[] = ['brief', 'caption', 'hashtags', 'image', 'alt_text', 'objective', 'audience', 'visual_direction'];
const creativeFields: readonly string[] = ['objective', 'audience', 'visual_direction'];

const aiActive = computed(() => props.ai?.current?.status === 'QUEUED' || props.ai?.current?.status === 'RUNNING');
const readonly = computed(() => props.actions.edit !== null || aiActive.value);
const errors = computed(() => form.errors as Partial<Record<FieldName | 'image' | 'revision', string>>);

// A new revision (saved from Ready, or written by AI) becomes the baseline.
watch(
    () => props.revision.id,
    () => {
        Object.assign(form, valuesOf(props.revision), { image: null });
        form.defaults();
        form.clearErrors();
    },
);

/** Moves focus to a field by its request name, opening Detail kreatif first when the field lives there. */
async function focusField(name: string): Promise<void> {
    if (creativeFields.includes(name)) {
        creativeOpen.value = true;
        await nextTick();
        // The sheet animates in and traps focus on open; wait a frame so the field is focusable.
        await new Promise((resolve) => window.requestAnimationFrame(resolve));
    }
    document.getElementById(name === 'image' ? 'content-image' : `content-${name}`)?.focus();
}

function focusFirstError(): void {
    const first = order.find((name) => errors.value[name]);
    if (first) void focusField(first);
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
                focusFirstError();
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

const discarding = ref(false);
function cancelEdit(): void {
    if (form.isDirty) discarding.value = true;
    else emit('cancelEdit');
}
</script>

<template>
    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_18rem] xl:grid-cols-[minmax(0,1fr)_20rem]" data-testid="draft-workspace">
        <section aria-labelledby="draft-title" class="min-w-0 space-y-6 rounded-xl border bg-card p-4 sm:p-5" data-testid="content-editor">
            <h2 id="draft-title" class="sr-only">{{ editingReady ? 'Edit konten' : 'Buat konten' }}</h2>
            <p v-if="editingReady" class="flex items-start gap-2 rounded-lg bg-warning-surface p-3 text-sm text-warning" data-testid="ready-edit-note">
                <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />Anda mengedit konten yang sudah siap posting. Menyimpan perubahan membuat
                revisi baru dan mengembalikan konten ke Draft.
            </p>
            <p v-else-if="revision.generatedBy" class="flex items-start gap-2 rounded-lg bg-info-surface p-3 text-sm text-info" data-testid="ai-draft-note">
                <Sparkles class="mt-0.5 size-4 shrink-0" aria-hidden="true" />Draft ini ditulis AI ({{
                    revision.generatedBy.model ?? revision.generatedBy.provider
                }}). Periksa fakta dan gaya bahasa sebelum menandai siap posting.
            </p>

            <div class="space-y-3">
                <FormField id="content-brief" label="Brief" :error="errors.brief" :hint="briefField?.hint">
                    <template #actions>
                        <span id="content-brief-count" class="text-xs text-muted-foreground tabular"
                            >{{ formatNumber(form.brief.length) }}/{{ formatNumber(briefField?.max ?? 0) }}</span
                        >
                    </template>
                    <template #default="{ describedBy, invalid }">
                        <Textarea
                            id="content-brief"
                            v-model="form.brief"
                            :rows="briefField?.rows"
                            :maxlength="briefField?.max"
                            :readonly="readonly"
                            :aria-describedby="`${describedBy ?? ''} content-brief-count`.trim()"
                            :aria-invalid="invalid"
                            :class="readonly ? 'bg-muted' : ''"
                        />
                    </template>
                </FormField>
                <ContentAiAssistant v-if="ai" :item="item" :revision="revision" :ai="ai" :prepare="prepareForAi" :dirty="form.isDirty" />
                <p v-else class="text-xs text-muted-foreground">AI dapat dipakai lagi setelah konten kembali ke Draft.</p>
            </div>

            <InstagramCompositionEditor
                v-model:caption="form.caption"
                v-model:hashtags="form.hashtags"
                :limits="limits"
                :readonly="readonly"
                :errors="{ caption: errors.caption, hashtags: errors.hashtags }"
            />

            <ContentMediaEditor
                v-model:image="form.image"
                v-model:alt-text="form.alt_text"
                :asset="revision.asset"
                :readonly="readonly"
                :errors="{ image: errors.image, alt_text: errors.alt_text }"
                :visual-direction="revision.visualDirection"
            />

            <BrandCheckSummary :findings="findings" :version="brandRulesVersion" :stale="form.isDirty" />
        </section>

        <ContentReadinessPanel
            class="lg:sticky lg:top-6"
            :item="item"
            :revision="revision"
            :findings="findings"
            :actions="actions"
            :dirty="form.isDirty"
            :saving="form.processing"
            :ai-active="aiActive"
            :errors="errors"
            :editing-ready="editingReady"
            @save="save"
            @cancel-edit="cancelEdit"
            @focus-field="focusField"
        />

        <CreativeDetailsSheet
            v-model:open="creativeOpen"
            v-model:objective="form.objective"
            v-model:audience="form.audience"
            v-model:visual-direction="form.visual_direction"
            :readonly="readonly"
            :saving="form.processing"
            :errors="{ objective: errors.objective, audience: errors.audience, visual_direction: errors.visual_direction }"
            @save="save"
        />
        <ConfirmDialog
            v-model:open="discarding"
            title="Buang perubahan yang belum disimpan?"
            description="Konten tetap siap posting dengan revisi yang sudah ada. Perubahan yang belum disimpan hilang."
            confirm-label="Buang perubahan"
            destructive
            @confirm="((discarding = false), emit('cancelEdit'))"
        />
    </div>
</template>
