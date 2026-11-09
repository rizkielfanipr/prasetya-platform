<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { dueLabel, postingSteps } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import type { ContentActions, ContentItem, ContentRevision, PostingStep } from '@/types/content';
import { Download, ExternalLink, Share2 } from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import ContentPublishDialog from './ContentPublishDialog.vue';
import CopyButton from './CopyButton.vue';

/**
 * Everything needed to post by hand, from the revision marked Ready only (ADR-0012). The checklist is ticked as
 * you go, survives switching to the Instagram app, and is sent as evidence when the post is marked published.
 */
const props = defineProps<{ item: ContentItem; revision: ContentRevision; actions: ContentActions }>();
const publishOpen = ref(false);

const captionWithTags = computed(() => [props.revision.caption, props.revision.hashtags].filter((part) => part).join('\n\n'));
const storageKey = computed(() => `content-checklist:${props.item.id}:${props.revision.id}`);
const checked = ref<PostingStep[]>([]);
const complete = computed(() => postingSteps.every((step) => checked.value.includes(step.value)));
const due = computed(() => dueLabel(props.item.dueState, 0));

onMounted(() => {
    try {
        const saved: unknown = JSON.parse(window.sessionStorage.getItem(storageKey.value) ?? '[]');
        checked.value = Array.isArray(saved) ? postingSteps.map((s) => s.value).filter((v) => saved.includes(v)) : [];
    } catch {
        checked.value = [];
    }
});
watch(checked, (value) => window.sessionStorage.setItem(storageKey.value, JSON.stringify(value)), { deep: true });

function toggle(step: PostingStep, on: boolean): void {
    checked.value = on ? [...checked.value.filter((v) => v !== step), step] : checked.value.filter((v) => v !== step);
}

// Phones can hand the image straight to Instagram; the caption goes to the clipboard first.
const canShare = ref(false);
onMounted(() => {
    const probe = new File([''], 'probe.png', { type: 'image/png' });
    canShare.value = typeof navigator.canShare === 'function' && navigator.canShare({ files: [probe] });
});
const sharing = ref(false);
async function share(): Promise<void> {
    const asset = props.revision.asset;
    if (!asset) return;
    sharing.value = true;
    try {
        if (captionWithTags.value) await navigator.clipboard.writeText(captionWithTags.value);
        const blob = await (await fetch(asset.url, { credentials: 'same-origin' })).blob();
        await navigator.share({ files: [new File([blob], asset.fileName, { type: asset.mimeType })] });
        toast.success('Caption sudah disalin. Tempel di Instagram setelah memilih gambar.');
    } catch (error) {
        if (!(error instanceof DOMException && error.name === 'AbortError')) toast.error('Gambar tidak dapat dibagikan. Unduh gambarnya lalu unggah manual.');
    } finally {
        sharing.value = false;
    }
}
</script>

<template>
    <div id="paket-posting" class="space-y-5">
        <div v-if="item.publication" class="space-y-1 rounded-lg bg-success-surface p-3 text-sm text-success" data-testid="publication">
            <p class="font-semibold">Diposting {{ formatDateTime(item.publication.publishedAt) }} (revisi {{ item.publication.revisionNumber }})</p>
            <p>Ditandai oleh {{ item.publication.actor }} · {{ formatDateTime(item.publication.recordedAt) }}</p>
            <p v-if="item.publication.checklist">Checklist posting dikonfirmasi ({{ item.publication.checklist.length }}/{{ postingSteps.length }}).</p>
            <a
                v-if="item.publication.url"
                :href="item.publication.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-1 font-semibold underline"
            >
                Buka di Instagram<ExternalLink class="size-3.5" aria-hidden="true" /><span class="sr-only">(tab baru)</span>
            </a>
            <p v-if="item.publication.note">Catatan: {{ item.publication.note }}</p>
        </div>
        <p
            v-else-if="due"
            class="rounded-lg p-3 text-sm font-semibold"
            :class="item.dueState === 'overdue' ? 'bg-danger-surface text-danger' : 'bg-warning-surface text-warning'"
            data-testid="due-banner"
        >
            {{ due }}: rencana {{ formatDateTime(item.plannedAt) }}. Posting manual, lalu tandai di bawah.
        </p>

        <div class="flex flex-wrap gap-2">
            <Button v-if="revision.asset && canShare && !item.publication" size="sm" class="gap-1.5" :disabled="sharing" :aria-busy="sharing" @click="share">
                <Share2 class="size-4" aria-hidden="true" />Bagikan ke Instagram
            </Button>
            <Button
                v-if="revision.asset"
                as="a"
                :href="revision.asset.downloadUrl"
                :download="revision.asset.fileName"
                variant="outline"
                size="sm"
                class="gap-1.5"
            >
                <Download class="size-4" aria-hidden="true" />Unduh gambar
            </Button>
            <CopyButton :text="captionWithTags" label="Salin caption + hashtag" done="Caption dan hashtag disalin." />
            <CopyButton :text="revision.caption ?? ''" label="Salin caption" done="Caption disalin." />
            <CopyButton :text="revision.hashtags ?? ''" label="Salin hashtag" done="Hashtag disalin." />
            <CopyButton :text="revision.altText ?? ''" label="Salin alt text" done="Alt text disalin." />
        </div>

        <dl class="space-y-3 text-sm">
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Caption</dt>
                <dd class="mt-1 break-words whitespace-pre-line">{{ revision.caption ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Hashtag</dt>
                <dd class="mt-1 break-words">{{ revision.hashtags ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold text-muted-foreground">Alt text</dt>
                <dd class="mt-1 break-words">{{ revision.altText ?? '—' }}</dd>
            </div>
        </dl>

        <fieldset v-if="!item.publication" class="space-y-2" aria-describedby="checklist-hint">
            <legend class="text-sm font-semibold">Checklist posting ({{ checked.length }}/{{ postingSteps.length }})</legend>
            <p id="checklist-hint" class="text-xs text-muted-foreground">Semua langkah wajib dicentang; checklist ikut dicatat sebagai bukti.</p>
            <div v-for="step in postingSteps" :key="step.value" class="flex items-center gap-2">
                <Checkbox :id="`step-${step.value}`" :model-value="checked.includes(step.value)" @update:model-value="toggle(step.value, $event === true)" />
                <label :for="`step-${step.value}`" class="text-sm">{{ step.label }}</label>
            </div>
        </fieldset>

        <div v-if="!item.publication" class="flex flex-col items-start gap-1">
            <Button
                :disabled="actions.markPublished !== null || !complete"
                :aria-describedby="actions.markPublished ? 'reason-markPublished' : !complete ? 'reason-checklist' : undefined"
                @click="publishOpen = true"
            >
                Tandai sudah diposting
            </Button>
            <p v-if="actions.markPublished" id="reason-markPublished" class="text-xs text-muted-foreground">{{ actions.markPublished }}</p>
            <p v-else-if="!complete" id="reason-checklist" class="text-xs text-muted-foreground">Centang semua langkah checklist dulu.</p>
        </div>
        <ContentPublishDialog v-model:open="publishOpen" :item="item" :revision="revision" :checklist="checked" />
    </div>
</template>
