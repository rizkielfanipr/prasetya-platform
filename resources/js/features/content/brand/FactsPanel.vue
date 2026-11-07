<script setup lang="ts">
import EmptyState from '@/components/shared/EmptyState.vue';
import { Button } from '@/components/ui/button';
import ContentDecisionDialog from '@/features/content/ContentDecisionDialog.vue';
import { formatDate, formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ContentCampaign, ContentFact } from '@/types/content';
import { Plus } from '@lucide/vue';
import { computed, ref } from 'vue';
import FactDialog from './FactDialog.vue';

const props = defineProps<{ brandId: string; facts: ContentFact[]; campaigns: ContentCampaign[] }>();
const adding = ref(false);
const retiring = ref<ContentFact | null>(null);
const active = computed(() => props.facts.filter((fact) => fact.retiredAt === null));
const retired = computed(() => props.facts.filter((fact) => fact.retiredAt !== null));
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-2xl text-sm text-muted-foreground">
                Harga (Rp) di konten yang tidak tertulis di fakta yang berlaku adalah pelanggaran; persentase tanpa fakta mendapat peringatan.
            </p>
            <Button class="gap-2" @click="adding = true"><Plus class="size-4" aria-hidden="true" />Tambah fakta</Button>
        </div>
        <EmptyState
            v-if="facts.length === 0"
            title="Belum ada fakta disetujui"
            description="Tambahkan hanya fakta yang sudah disetujui pemilik, beserta sumbernya."
        />
        <ul v-else class="divide-y rounded-lg border" aria-label="Fakta berlaku">
            <li v-for="fact in active" :key="fact.id" class="flex flex-wrap items-start justify-between gap-3 p-4">
                <div class="min-w-0 flex-1 space-y-1">
                    <p class="font-medium break-words">{{ fact.statement }}</p>
                    <p class="text-xs text-muted-foreground">
                        Sumber: {{ fact.source }}<template v-if="fact.campaign"> · Kampanye {{ fact.campaign }}</template
                        ><template v-if="fact.validUntil"> · berlaku sampai {{ formatDate(`${fact.validUntil}T00:00:00+07:00`) }}</template> ·
                        {{ fact.createdBy }},
                        {{ formatDateTime(fact.createdAt) }}
                    </p>
                </div>
                <Button variant="outline" size="sm" @click="retiring = fact">Tidak berlaku</Button>
            </li>
        </ul>
        <details v-if="retired.length" class="rounded-lg border p-4">
            <summary class="cursor-pointer text-sm font-semibold">Fakta tidak berlaku ({{ retired.length }})</summary>
            <ul class="mt-3 space-y-3">
                <li v-for="fact in retired" :key="fact.id" class="text-sm">
                    <p class="break-words text-muted-foreground line-through">{{ fact.statement }}</p>
                    <p class="text-xs text-muted-foreground">{{ fact.retiredBy }}, {{ formatDateTime(fact.retiredAt) }} — {{ fact.retiredReason }}</p>
                </li>
            </ul>
        </details>

        <FactDialog v-model:open="adding" :brand-id="brandId" :campaigns="campaigns" />
        <ContentDecisionDialog
            v-if="retiring"
            :key="retiring.id"
            :open="true"
            :url="routes.contentFactRetire(brandId, retiring.id)"
            revision-id=""
            title="Fakta ini tidak berlaku lagi?"
            :description="`“${retiring.statement}” tetap tercatat, tetapi konten tidak bisa lagi memakainya.`"
            confirm-label="Tidak berlaku"
            note-label="Alasan"
            note-field="reason"
            note-required
            destructive
            @update:open="(value) => !value && (retiring = null)"
        />
    </div>
</template>
