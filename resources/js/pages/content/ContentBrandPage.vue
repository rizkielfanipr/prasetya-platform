<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import AuditTimeline from '@/components/shared/AuditTimeline.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import BrandRulesPanel from '@/features/content/brand/BrandRulesPanel.vue';
import FactsPanel from '@/features/content/brand/FactsPanel.vue';
import PillarDialog from '@/features/content/brand/PillarDialog.vue';
import ContentNav from '@/features/content/ContentNav.vue';
import { brandTabs } from '@/lib/content';
import { routes } from '@/lib/routes';
import { userStatuses } from '@/lib/status';
import type { AuditEntry } from '@/types/backoffice';
import type { BrandRules, BrandRulesVersion, BrandTab, ContentCampaign, ContentFact, ContentPillar } from '@/types/content';
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';

/** Brand & Knowledge (ADR-0011): persistent guidance, strategy, and approved knowledge for one brand. */
const props = defineProps<{
    brand: { id: string; code: string; name: string };
    brands: { id: string; code: string; name: string }[];
    tab: BrandTab;
    rules: BrandRules | null;
    versions: BrandRulesVersion[];
    pillars: ContentPillar[];
    campaigns: ContentCampaign[];
    facts: ContentFact[];
    activity: AuditEntry[];
}>();

const pillarOpen = ref(false);
const editingPillar = ref<ContentPillar | null>(null);
const activeShare = computed(() => props.pillars.filter((pillar) => pillar.isActive).reduce((sum, pillar) => sum + pillar.targetShare, 0));
const activePillars = computed(() => props.pillars.filter((pillar) => pillar.isActive).length);
const activeKnowledge = computed(() => props.facts.filter((fact) => fact.retiredAt === null).length);

function openPillar(pillar: ContentPillar | null): void {
    editingPillar.value = pillar;
    pillarOpen.value = true;
}
</script>

<template>
    <AppLayout title="Content Studio" description="Panduan, strategi, dan knowledge yang menjadi acuan konten brand.">
        <ContentNav current="brands" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold">{{ brand.name }}</h2>
            <label v-if="brands.length > 1" class="flex items-center gap-2 text-sm font-semibold">
                Brand
                <select
                    :value="brand.id"
                    class="h-9 rounded-md border bg-card px-2 text-sm font-normal"
                    @change="router.get(routes.contentBrand(($event.target as HTMLSelectElement).value))"
                >
                    <option v-for="option in brands" :key="option.id" :value="option.id">{{ option.name }}</option>
                </select>
            </label>
        </div>

        <section class="grid gap-3 md:grid-cols-3" aria-label="Ringkasan Brand & Knowledge">
            <div class="rounded-lg border bg-card p-4">
                <p class="text-xs font-semibold text-muted-foreground">Panduan Brand</p>
                <p class="mt-1 text-lg font-bold">{{ rules ? `Versi ${rules.version}` : 'Belum diisi' }}</p>
                <p class="text-sm text-muted-foreground">Cara brand berbicara dan batas konten.</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <p class="text-xs font-semibold text-muted-foreground">Strategi Konten</p>
                <p class="mt-1 text-lg font-bold">{{ activePillars }} pilar · {{ activeShare }}%</p>
                <p class="text-sm text-muted-foreground">Komposisi target konten aktif.</p>
            </div>
            <div class="rounded-lg border bg-card p-4">
                <p class="text-xs font-semibold text-muted-foreground">Knowledge Base</p>
                <p class="mt-1 text-lg font-bold">{{ activeKnowledge }} knowledge aktif</p>
                <p class="text-sm text-muted-foreground">Informasi yang boleh dipakai konten dan AI.</p>
            </div>
        </section>

        <nav aria-label="Bagian brand" class="flex max-w-full gap-1 overflow-x-auto border-b">
            <Link
                v-for="item in brandTabs"
                :key="item.value"
                :href="`${routes.contentBrand(brand.id)}?tab=${item.value}`"
                preserve-scroll
                class="-mb-px inline-flex h-11 shrink-0 items-center border-b-2 px-3 text-sm whitespace-nowrap hover:text-foreground focus-visible:rounded-t-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                :class="item.value === tab ? 'border-primary-strong font-semibold text-foreground' : 'border-transparent font-medium text-muted-foreground'"
                :aria-current="item.value === tab ? 'page' : undefined"
                >{{ item.label }}</Link
            >
        </nav>

        <PanelCard
            v-if="tab === 'rules'"
            title="Panduan Brand"
            description="Setiap perubahan disimpan sebagai versi baru; revisi yang sudah ditandai siap posting tetap merujuk versinya."
        >
            <BrandRulesPanel :key="rules?.version ?? 0" :brand-id="brand.id" :rules="rules" :versions="versions" />
        </PanelCard>

        <PanelCard v-else-if="tab === 'pillars'" title="Strategi Konten" :description="`Total target pilar aktif: ${activeShare}% dari 100%.`">
            <template #actions
                ><Button class="gap-2" @click="openPillar(null)"><Plus class="size-4" aria-hidden="true" />Tambah pilar</Button></template
            >
            <EmptyState v-if="pillars.length === 0" title="Belum ada pilar" description="Pilar membantu menjaga variasi konten tiap minggu." />
            <ul v-else class="divide-y rounded-lg border">
                <li v-for="pillar in pillars" :key="pillar.id" class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">
                            {{ pillar.name }} <span class="font-normal text-muted-foreground">· target {{ pillar.targetShare }}%</span>
                        </p>
                        <p v-if="pillar.description" class="text-sm break-words text-muted-foreground">{{ pillar.description }}</p>
                    </div>
                    <StatusBadge :status="pillar.isActive ? userStatuses.active : userStatuses.inactive" />
                    <Button variant="outline" size="sm" class="gap-1.5" :aria-label="`Ubah pilar ${pillar.name}`" @click="openPillar(pillar)"
                        ><Pencil class="size-3.5" aria-hidden="true" />Ubah</Button
                    >
                </li>
            </ul>
            <PillarDialog v-model:open="pillarOpen" :brand-id="brand.id" :pillar="editingPillar" />
        </PanelCard>

        <PanelCard v-else-if="tab === 'facts'" title="Knowledge Base" description="Informasi terverifikasi yang boleh digunakan oleh konten dan AI.">
            <FactsPanel :brand-id="brand.id" :facts="facts" :campaigns="campaigns" />
        </PanelCard>

        <PanelCard
            v-else
            title="Aktivitas"
            description="Perubahan panduan, strategi, campaign, dan knowledge brand ini, terbaru di atas (maksimal 100 catatan)."
        >
            <AuditTimeline :entries="activity" />
        </PanelCard>
    </AppLayout>
</template>
