<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import ContentPublishDialog from '@/features/content/ContentPublishDialog.vue';
import CopyButton from '@/features/content/CopyButton.vue';
import { contentActionLabels, dueLabel } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import type { ContentActions, ContentItem, ContentRevision, RuleFinding } from '@/types/content';
import { ChevronDown, Download, Pencil, Share2 } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import BrandCheckSummary from './BrandCheckSummary.vue';
import ContentSnapshot from './ContentSnapshot.vue';

/**
 * The Ready workspace (ADR-0013): the posting package, not the editor. Image, copy, download, and share where the
 * device can share files, then the one primary action, Tandai sudah diposting. Edit konten asks first, because the
 * next save makes a new revision and returns the item to Draft; the frozen Ready revision is never edited in place.
 */
const props = defineProps<{
    item: ContentItem;
    revision: ContentRevision;
    actions: ContentActions;
    findings: RuleFinding[];
    brandRulesVersion: number | null;
}>();
const emit = defineEmits<{ edit: [] }>();

const publishOpen = ref(false);
const confirmEdit = ref(false);
const captionWithTags = computed(() => [props.revision.caption, props.revision.hashtags].filter((part) => part).join('\n\n'));
const due = computed(() => dueLabel(props.item.dueState, props.item.daysLate ?? 0));
const hasFindings = computed(() => props.findings.some((finding) => finding.code !== 'no_profile'));

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

async function copyOther(text: string, done: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(text);
        toast.success(done);
    } catch {
        toast.error('Teks tidak dapat disalin. Pilih teksnya lalu salin manual.');
    }
}
</script>

<template>
    <section id="paket-posting" aria-labelledby="posting-title" class="space-y-5 rounded-xl border bg-card p-4 sm:p-5" data-testid="ready-workspace">
        <div class="space-y-2">
            <h2 id="posting-title" class="text-lg font-bold">Paket posting</h2>
            <p class="text-sm" data-testid="posting-schedule">
                <span v-if="due" class="font-semibold" :class="item.dueState === 'overdue' ? 'text-danger' : 'text-warning'" data-testid="due-banner"
                    >{{ due }} ·
                </span>
                Rencana {{ formatDateTime(item.plannedAt) }}. Posting manual di Instagram, lalu tandai sudah diposting.
            </p>
        </div>

        <ContentSnapshot :revision="revision">
            <div class="flex flex-wrap gap-2" data-testid="posting-actions">
                <Button v-if="revision.asset && canShare" variant="secondary" class="h-11 gap-1.5" :disabled="sharing" :aria-busy="sharing" @click="share">
                    <Share2 class="size-4" aria-hidden="true" />Bagikan ke Instagram
                </Button>
                <Button
                    v-if="revision.asset"
                    as="a"
                    :href="revision.asset.downloadUrl"
                    :download="revision.asset.fileName"
                    variant="outline"
                    class="h-11 gap-1.5"
                >
                    <Download class="size-4" aria-hidden="true" />Unduh gambar
                </Button>
                <CopyButton :text="captionWithTags" label="Salin caption" done="Caption dan hashtag disalin." />
                <DropdownMenu v-if="revision.hashtags || revision.altText">
                    <DropdownMenuTrigger as-child>
                        <Button variant="outline" class="h-11 gap-1.5">Salin lainnya<ChevronDown class="size-4" aria-hidden="true" /></Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start" class="w-52">
                        <DropdownMenuItem
                            v-if="revision.hashtags"
                            class="min-h-11 cursor-pointer"
                            @select="copyOther(revision.hashtags ?? '', 'Hashtag disalin.')"
                            >Salin hashtag</DropdownMenuItem
                        >
                        <DropdownMenuItem
                            v-if="revision.altText"
                            class="min-h-11 cursor-pointer"
                            @select="copyOther(revision.altText ?? '', 'Alt text disalin.')"
                            >Salin alt text</DropdownMenuItem
                        >
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
            <p class="text-xs text-muted-foreground">Salin caption sudah termasuk hashtag. Alt text diisi di Pengaturan lanjutan Instagram.</p>
        </ContentSnapshot>

        <BrandCheckSummary v-if="hasFindings" :findings="findings" :version="brandRulesVersion" />

        <div class="flex flex-wrap items-start gap-3 border-t pt-4">
            <div class="flex flex-col gap-1">
                <Button
                    class="h-11"
                    :disabled="actions.markPublished !== null"
                    :aria-describedby="actions.markPublished ? 'reason-markPublished' : undefined"
                    data-testid="mark-published"
                    @click="publishOpen = true"
                    >{{ contentActionLabels.markPublished }}</Button
                >
                <p v-if="actions.markPublished" id="reason-markPublished" class="max-w-xs text-xs text-muted-foreground">{{ actions.markPublished }}</p>
            </div>
            <Button v-if="actions.edit === null" variant="ghost" class="h-11 gap-1.5" data-testid="edit-ready" @click="confirmEdit = true">
                <Pencil class="size-4" aria-hidden="true" />Edit konten
            </Button>
        </div>

        <ContentPublishDialog v-model:open="publishOpen" :item="item" :revision="revision" />
        <ConfirmDialog
            v-model:open="confirmEdit"
            title="Edit konten yang sudah siap posting?"
            :description="`Revisi ${revision.number} tetap tersimpan. Menyimpan perubahan membuat revisi baru dan mengembalikan konten ke Draft, jadi konten perlu ditandai siap posting lagi.`"
            confirm-label="Edit konten"
            @confirm="((confirmEdit = false), emit('edit'))"
        />
    </section>
</template>
