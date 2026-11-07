<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import AuditTimeline from '@/components/shared/AuditTimeline.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import BrandRulesPanel from '@/features/content/brand/BrandRulesPanel.vue';
import CampaignDialog from '@/features/content/brand/CampaignDialog.vue';
import FactsPanel from '@/features/content/brand/FactsPanel.vue';
import PillarDialog from '@/features/content/brand/PillarDialog.vue';
import ContentNav from '@/features/content/ContentNav.vue';
import { brandTabs, campaignPriorities } from '@/lib/content';
import { formatDate } from '@/lib/format';
import { routes } from '@/lib/routes';
import { userStatuses } from '@/lib/status';
import type { AuditEntry } from '@/types/backoffice';
import type { BrandRules, BrandRulesVersion, BrandTab, CampaignPriority, ContentCampaign, ContentFact, ContentPillar } from '@/types/content';
import { Link, router } from '@inertiajs/vue3';
import { Pencil, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';

/** Brand & Template (ADR-0011, phase 2): the rules, pillars, campaigns, and facts content is checked against. */
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
    priorities: CampaignPriority[];
}>();

const pillarOpen = ref(false);
const editingPillar = ref<ContentPillar | null>(null);
const campaignOpen = ref(false);
const editingCampaign = ref<ContentCampaign | null>(null);
const activeShare = computed(() => props.pillars.filter((pillar) => pillar.isActive).reduce((sum, pillar) => sum + pillar.targetShare, 0));
const day = (date: string) => formatDate(`${date}T00:00:00+07:00`);

function openPillar(pillar: ContentPillar | null): void {
    editingPillar.value = pillar;
    pillarOpen.value = true;
}

function openCampaign(campaign: ContentCampaign | null): void {
    editingCampaign.value = campaign;
    campaignOpen.value = true;
}
</script>

<template>
    <AppLayout title="Content Studio" description="Aturan brand, pilar, kampanye, dan fakta yang menjadi acuan konten.">
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
            title="Aturan brand"
            description="Setiap perubahan disimpan sebagai versi baru; revisi yang sudah diajukan tetap merujuk versinya."
        >
            <BrandRulesPanel :key="rules?.version ?? 0" :brand-id="brand.id" :rules="rules" :versions="versions" />
        </PanelCard>

        <PanelCard v-else-if="tab === 'pillars'" title="Pilar konten" :description="`Total target pilar aktif: ${activeShare}% dari 100%.`">
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

        <PanelCard v-else-if="tab === 'campaigns'" title="Kampanye">
            <template #actions
                ><Button class="gap-2" @click="openCampaign(null)"><Plus class="size-4" aria-hidden="true" />Tambah kampanye</Button></template
            >
            <EmptyState v-if="campaigns.length === 0" title="Belum ada kampanye" />
            <ul v-else class="divide-y rounded-lg border">
                <li v-for="campaign in campaigns" :key="campaign.id" class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ campaign.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ day(campaign.startsOn) }} – {{ day(campaign.endsOn) }} · prioritas {{ campaignPriorities[campaign.priority].toLowerCase() }}
                        </p>
                        <p v-if="campaign.objective" class="text-sm break-words">{{ campaign.objective }}</p>
                    </div>
                    <StatusBadge :status="campaign.isActive ? userStatuses.active : userStatuses.inactive" />
                    <Button variant="outline" size="sm" class="gap-1.5" :aria-label="`Ubah kampanye ${campaign.name}`" @click="openCampaign(campaign)"
                        ><Pencil class="size-3.5" aria-hidden="true" />Ubah</Button
                    >
                </li>
            </ul>
            <CampaignDialog v-model:open="campaignOpen" :brand-id="brand.id" :campaign="editingCampaign" :priorities="priorities" />
        </PanelCard>

        <PanelCard v-else-if="tab === 'facts'" title="Fakta disetujui" description="Satu-satunya sumber harga, nama produk, dan promo untuk konten.">
            <FactsPanel :brand-id="brand.id" :facts="facts" :campaigns="campaigns" />
        </PanelCard>

        <PanelCard v-else title="Aktivitas" description="Perubahan aturan, pilar, kampanye, dan fakta brand ini, terbaru di atas (maksimal 100 catatan).">
            <AuditTimeline :entries="activity" />
        </PanelCard>
    </AppLayout>
</template>
