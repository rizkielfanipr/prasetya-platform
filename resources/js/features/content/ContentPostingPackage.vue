<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { dueLabel, postingSteps } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import type { ContentActions, ContentItem, ContentRevision } from '@/types/content';
import { Download, ExternalLink, Share2 } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import ContentPublishDialog from './ContentPublishDialog.vue';
import CopyButton from './CopyButton.vue';

/**
 * Everything needed to post by hand, from the revision marked Ready only (ADR-0012, phase 4): the image, Bagikan ke
 * Instagram where the device can share files, Unduh gambar, the copy buttons, and Tandai sudah diposting. There is no
 * required checklist; a checklist recorded on an older publication is still shown as evidence.
 */
const props = defineProps<{ item: ContentItem; revision: ContentRevision; actions: ContentActions }>();
const publishOpen = ref(false);

const captionWithTags = computed(() => [props.revision.caption, props.revision.hashtags].filter((part) => part).join('\n\n'));
const due = computed(() => dueLabel(props.item.dueState, 0));

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
            {{ due }}: rencana {{ formatDateTime(item.plannedAt) }}. Posting manual, lalu tandai sudah diposting.
        </p>

        <div class="grid gap-5 sm:grid-cols-[10rem_minmax(0,1fr)]">
            <img
                v-if="revision.asset"
                :src="revision.asset.url"
                :alt="revision.altText ?? ''"
                class="aspect-[4/5] w-40 rounded-lg border object-cover"
                loading="lazy"
                data-testid="posting-image"
            />
            <p v-else class="flex aspect-[4/5] w-40 items-center justify-center rounded-lg border p-3 text-center text-xs text-muted-foreground">
                Belum ada gambar
            </p>
            <div class="min-w-0 space-y-4">
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="revision.asset && canShare && !item.publication"
                        size="sm"
                        class="gap-1.5"
                        :disabled="sharing"
                        :aria-busy="sharing"
                        @click="share"
                    >
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
                    <CopyButton :text="captionWithTags" label="Salin caption" done="Caption dan hashtag disalin." />
                    <CopyButton v-if="revision.hashtags" :text="revision.hashtags" label="Salin hashtag" done="Hashtag disalin." />
                    <CopyButton v-if="revision.altText" :text="revision.altText" label="Salin alt text" done="Alt text disalin." />
                </div>
                <p class="text-xs text-muted-foreground">Salin caption sudah termasuk hashtag. Alt text diisi di Pengaturan lanjutan Instagram.</p>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs font-semibold text-muted-foreground">Caption</dt>
                        <dd class="mt-1 break-words whitespace-pre-line">{{ revision.caption ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold text-muted-foreground">Hashtag</dt>
                        <dd class="mt-1 break-words">{{ revision.hashtags ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div v-if="!item.publication" class="flex flex-col items-start gap-1">
            <Button
                :disabled="actions.markPublished !== null"
                :aria-describedby="actions.markPublished ? 'reason-markPublished' : undefined"
                @click="publishOpen = true"
            >
                Tandai sudah diposting
            </Button>
            <p v-if="actions.markPublished" id="reason-markPublished" class="text-xs text-muted-foreground">{{ actions.markPublished }}</p>
        </div>
        <ContentPublishDialog v-model:open="publishOpen" :item="item" :revision="revision" />
    </div>
</template>
