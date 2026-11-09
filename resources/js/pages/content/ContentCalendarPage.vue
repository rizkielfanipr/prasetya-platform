<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import ErrorState from '@/components/shared/ErrorState.vue';
import FilterBar, { type ActiveFilter } from '@/components/shared/FilterBar.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button, buttonVariants } from '@/components/ui/button';
import { useUrlFilters } from '@/composables/useUrlFilters';
import CampaignDialog from '@/features/content/brand/CampaignDialog.vue';
import ContentComposition from '@/features/content/ContentComposition.vue';
import ContentCreateDialog from '@/features/content/ContentCreateDialog.vue';
import ContentFilterFields from '@/features/content/ContentFilterFields.vue';
import ContentNav from '@/features/content/ContentNav.vue';
import ContentWeekView from '@/features/content/ContentWeekView.vue';
import { campaignPriorities, contentStatuses } from '@/lib/content';
import { formatDate } from '@/lib/format';
import { routes } from '@/lib/routes';
import { userStatuses } from '@/lib/status';
import type { CampaignPriority, CompositionEntry, ContentBrandOption, ContentCampaign, ContentFilters, ContentRow, ContentWeek } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Pencil, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';

/** Kalender (ADR-0011): one week of planned posts. A planned time passing changes nothing. */
const props = defineProps<{
    filters: ContentFilters & { week: string };
    week: ContentWeek;
    items: ContentRow[];
    composition: CompositionEntry[];
    brands: (ContentBrandOption & { id: string })[];
    manageCampaigns: boolean;
    priorities: CampaignPriority[];
}>();

const campaignOpen = ref(false);
const campaignBrand = ref<string | null>(null);
const editingCampaign = ref<ContentCampaign | null>(null);

const { apply, loading, failed } = useUrlFilters(
    () => routes.contentCalendar,
    () => ({ ...props.filters }),
    ['filters', 'week', 'items', 'composition'],
);

const query = (week: string) => Object.fromEntries(Object.entries({ ...props.filters, week }).filter(([, value]) => value !== null));
const brandName = (code: string) => props.brands.find((brand) => brand.code === code)?.name ?? code;
const pillarName = (id: string) => props.brands.flatMap((brand) => brand.pillars).find((pillar) => pillar.id === id)?.name ?? id;
const campaignName = (id: string) => props.brands.flatMap((brand) => brand.campaigns).find((campaign) => campaign.id === id)?.name ?? id;
const active = computed<ActiveFilter[]>(() =>
    [
        props.filters.brand ? { key: 'brand', label: `Brand: ${brandName(props.filters.brand)}` } : null,
        props.filters.pillar ? { key: 'pillar', label: `Pilar: ${pillarName(props.filters.pillar)}` } : null,
        props.filters.campaign ? { key: 'campaign', label: `Kampanye: ${campaignName(props.filters.campaign)}` } : null,
        props.filters.status ? { key: 'status', label: `Status: ${contentStatuses[props.filters.status].label}` } : null,
    ].filter((filter): filter is ActiveFilter => filter !== null),
);
const isCurrentWeek = computed(() => props.week.days.includes(props.week.today));
const range = computed(() => `${formatDate(`${props.week.start}T00:00:00+07:00`)} – ${formatDate(`${props.week.end}T00:00:00+07:00`)}`);
const navClass = buttonVariants({ variant: 'outline', size: 'sm' });
const day = (date: string) => formatDate(`${date}T00:00:00+07:00`);

function openCampaign(brandId: string, campaign: ContentCampaign | null): void {
    campaignBrand.value = brandId;
    editingCampaign.value = campaign;
    campaignOpen.value = true;
}
</script>

<template>
    <AppLayout title="Content Studio" description="Rencanakan, tinjau, dan catat posting Instagram. Posting tetap dilakukan manual.">
        <ContentNav current="calendar" />

        <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card p-4">
            <div>
                <p class="font-semibold">Perencanaan Konten</p>
                <p class="text-sm text-muted-foreground">Kelola campaign berbatas waktu bersama kalender dan jadwal konten.</p>
            </div>
            <Link
                :href="routes.contentCalendar"
                :data="{ ...query(week.start), manage_campaigns: manageCampaigns ? null : 1 }"
                :class="buttonVariants({ variant: manageCampaigns ? 'outline' : 'default' })"
                preserve-scroll
            >
                {{ manageCampaigns ? 'Tutup campaign' : 'Kelola campaign' }}
            </Link>
        </div>

        <PanelCard v-if="manageCampaigns" title="Campaign" description="Program sementara dengan tujuan dan periode yang menjadi konteks jadwal konten.">
            <div class="space-y-5">
                <section v-for="brand in brands" :key="brand.id" class="space-y-3" :aria-labelledby="`campaign-brand-${brand.id}`">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 :id="`campaign-brand-${brand.id}`" class="font-semibold">{{ brand.name }}</h3>
                        <Button size="sm" class="gap-1.5" @click="openCampaign(brand.id, null)">
                            <Plus class="size-3.5" aria-hidden="true" />Tambah campaign
                        </Button>
                    </div>
                    <EmptyState v-if="brand.campaigns.length === 0" title="Belum ada campaign" />
                    <ul v-else class="divide-y rounded-lg border">
                        <li v-for="campaign in brand.campaigns" :key="campaign.id" class="flex flex-wrap items-center justify-between gap-3 p-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold">{{ campaign.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ day(campaign.startsOn) }} – {{ day(campaign.endsOn) }} · prioritas
                                    {{ campaignPriorities[campaign.priority].toLowerCase() }}
                                </p>
                                <p v-if="campaign.objective" class="text-sm break-words">{{ campaign.objective }}</p>
                            </div>
                            <StatusBadge :status="campaign.isActive ? userStatuses.active : userStatuses.inactive" />
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1.5"
                                :aria-label="`Ubah campaign ${campaign.name}`"
                                @click="openCampaign(brand.id, campaign)"
                            >
                                <Pencil class="size-3.5" aria-hidden="true" />Ubah
                            </Button>
                        </li>
                    </ul>
                </section>
            </div>
            <CampaignDialog v-if="campaignBrand" v-model:open="campaignOpen" :brand-id="campaignBrand" :campaign="editingCampaign" :priorities="priorities" />
        </PanelCard>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="routes.contentCalendar" :data="query(week.previous)" :class="navClass" aria-label="Minggu sebelumnya" preserve-scroll
                    ><ChevronLeft class="size-4" aria-hidden="true"
                /></Link>
                <h2 class="min-w-52 text-center text-base font-semibold" aria-live="polite">{{ range }}</h2>
                <Link :href="routes.contentCalendar" :data="query(week.next)" :class="navClass" aria-label="Minggu berikutnya" preserve-scroll
                    ><ChevronRight class="size-4" aria-hidden="true"
                /></Link>
                <Link v-if="!isCurrentWeek" :href="routes.contentCalendar" :data="query(week.today)" :class="navClass">Minggu ini</Link>
            </div>
            <ContentCreateDialog :brands="brands" :default-day="isCurrentWeek ? week.today : week.start" />
        </div>

        <FilterBar :active="active" @remove="apply({ [$event]: null })" @clear="apply({ brand: null, pillar: null, campaign: null, status: null })">
            <ContentFilterFields :filters="filters" :brands="brands" @apply="apply" />
        </FilterBar>

        <ErrorState v-if="failed" retryable @retry="apply({})" />
        <template v-else>
            <ContentComposition :entries="composition" />
            <ContentWeekView :week="week" :items="items" :loading="loading" />
        </template>
    </AppLayout>
</template>
