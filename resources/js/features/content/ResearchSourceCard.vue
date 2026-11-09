<script setup lang="ts">
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { sourceTrusts } from '@/lib/content';
import { formatDate } from '@/lib/format';
import type { ResearchSource } from '@/types/content';
import { ExternalLink } from '@lucide/vue';

/** One source. Its text came from the internet: shown as plain text, linked with no referrer and no follow. */
defineProps<{ source: ResearchSource }>();
</script>

<template>
    <div class="min-w-0 flex-1 space-y-1">
        <p class="flex flex-wrap items-center gap-2">
            <a
                :href="source.url"
                target="_blank"
                rel="noopener noreferrer nofollow"
                class="inline-flex items-center gap-1 font-semibold break-words text-primary-text hover:underline"
                >{{ source.title }}<ExternalLink class="size-3.5 shrink-0" aria-hidden="true" /><span class="sr-only">(tab baru)</span></a
            >
            <StatusBadge :status="sourceTrusts[source.trust]" />
        </p>
        <p class="text-xs text-muted-foreground">
            {{ source.domain }}<template v-if="source.publishedOn"> · terbit {{ formatDate(`${source.publishedOn}T00:00:00+07:00`) }}</template> ·
            {{ source.origin === 'MANUAL' ? 'ditambahkan manual' : 'dari pencarian' }}
        </p>
        <p v-if="source.summary" class="text-sm break-words text-muted-foreground">{{ source.summary }}</p>
        <p v-if="source.note" class="text-sm break-words">Catatan tim: {{ source.note }}</p>
    </div>
</template>
