<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { formatDateTime } from '@/lib/format';
import type { ContentActions, ContentItem, ContentRevision } from '@/types/content';
import { Download, ExternalLink } from '@lucide/vue';
import { computed, ref } from 'vue';
import ContentPublishDialog from './ContentPublishDialog.vue';
import CopyButton from './CopyButton.vue';

/** Everything needed to post by hand, from the approved revision only. The checklist is a guide and is not stored. */
const props = defineProps<{ item: ContentItem; revision: ContentRevision; actions: ContentActions }>();
const publishOpen = ref(false);

const captionWithTags = computed(() => [props.revision.caption, props.revision.hashtags].filter((part) => part).join('\n\n'));
const checklist = [
    { id: 'check-image', label: 'Gambar diunduh dari paket ini' },
    { id: 'check-caption', label: 'Caption dan hashtag ditempel apa adanya' },
    { id: 'check-alt', label: 'Alt text diisi di Pengaturan lanjutan Instagram' },
    { id: 'check-account', label: 'Diposting dari akun brand yang benar' },
];
const checked = ref<Record<string, boolean>>({});
const done = computed(() => checklist.filter((entry) => checked.value[entry.id] === true).length);
</script>

<template>
    <div class="space-y-5">
        <div v-if="item.publication" class="space-y-1 rounded-lg bg-success-surface p-3 text-sm text-success" data-testid="publication">
            <p class="font-semibold">Dipublikasikan {{ formatDateTime(item.publication.publishedAt) }} (revisi {{ item.publication.revisionNumber }})</p>
            <p>Ditandai oleh {{ item.publication.actor }} · {{ formatDateTime(item.publication.recordedAt) }}</p>
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

        <div class="flex flex-wrap gap-2">
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

        <fieldset v-if="!item.publication" class="space-y-2">
            <legend class="text-sm font-semibold">Checklist posting ({{ done }}/{{ checklist.length }})</legend>
            <div v-for="entry in checklist" :key="entry.id" class="flex items-center gap-2">
                <Checkbox :id="entry.id" :model-value="checked[entry.id] === true" @update:model-value="checked[entry.id] = $event === true" />
                <label :for="entry.id" class="text-sm">{{ entry.label }}</label>
            </div>
        </fieldset>

        <div v-if="!item.publication" class="flex flex-col items-start gap-1">
            <Button
                :disabled="actions.markPublished !== null"
                :aria-describedby="actions.markPublished ? 'reason-markPublished' : undefined"
                @click="publishOpen = true"
            >
                Tandai sudah dipublikasikan
            </Button>
            <p v-if="actions.markPublished" id="reason-markPublished" class="text-xs text-muted-foreground">{{ actions.markPublished }}</p>
        </div>
        <ContentPublishDialog v-model:open="publishOpen" :item="item" :revision="revision" />
    </div>
</template>
