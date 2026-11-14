<script setup lang="ts">
import type { ContentRevision } from '@/types/content';

/**
 * One revision as it is (or was) posted, read-only: the 4:5 image, caption, hashtags, and optionally alt text. Ready,
 * Published, and Archived show it; the slot holds the actions that belong next to the image (copy, share, download).
 */
withDefaults(defineProps<{ revision: ContentRevision; showAlt?: boolean; stacked?: boolean }>(), { showAlt: false, stacked: false });
</script>

<template>
    <div class="grid gap-5" :class="stacked ? '' : 'sm:grid-cols-[12rem_minmax(0,1fr)] lg:grid-cols-[14rem_minmax(0,1fr)]'" data-testid="content-snapshot">
        <div :class="stacked ? 'mx-auto w-full max-w-64' : 'mx-auto w-full max-w-56 sm:max-w-none'">
            <img
                v-if="revision.asset"
                :src="revision.asset.url"
                :alt="revision.altText ?? ''"
                :width="revision.asset.width"
                :height="revision.asset.height"
                class="aspect-[4/5] w-full rounded-lg border object-cover"
                data-testid="posting-image"
            />
            <p v-else class="flex aspect-[4/5] w-full items-center justify-center rounded-lg border bg-muted p-4 text-center text-sm text-muted-foreground">
                Belum ada gambar
            </p>
        </div>
        <div class="min-w-0 space-y-4">
            <slot />
            <dl class="space-y-3 text-sm">
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Caption</dt>
                    <dd class="mt-1 max-w-prose break-words whitespace-pre-line" data-testid="snapshot-caption">
                        {{ revision.caption ?? 'Belum ada caption.' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Hashtag</dt>
                    <dd class="mt-1 max-w-prose break-words">{{ revision.hashtags || 'Tanpa hashtag.' }}</dd>
                </div>
                <div v-if="showAlt">
                    <dt class="text-xs font-semibold text-muted-foreground">Alt text</dt>
                    <dd class="mt-1 max-w-prose break-words">{{ revision.altText || 'Belum ada alt text.' }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
