<script setup lang="ts">
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import AppLayout from '@/components/layout/AppLayout.vue';
import PageSection from '@/components/layout/PageSection.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import ErrorState from '@/components/shared/ErrorState.vue';
import FilterBar, { type ActiveFilter } from '@/components/shared/FilterBar.vue';
import SearchInput from '@/components/shared/SearchInput.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import TablePagination from '@/components/shared/TablePagination.vue';
import { Button, buttonVariants } from '@/components/ui/button';
import { useUrlFilters } from '@/composables/useUrlFilters';
import CampaignDialog from '@/features/content/brand/CampaignDialog.vue';
import ContentComposition from '@/features/content/ContentComposition.vue';
import ContentCreateDialog from '@/features/content/ContentCreateDialog.vue';
import ContentFilterFields from '@/features/content/ContentFilterFields.vue';
import ContentNav from '@/features/content/ContentNav.vue';
import ContentReminders from '@/features/content/ContentReminders.vue';
import ContentStatusFilter from '@/features/content/ContentStatusFilter.vue';
import ContentTable from '@/features/content/ContentTable.vue';
import ContentWeekView from '@/features/content/ContentWeekView.vue';
import { campaignPeriods, campaignPriorities, contentStatuses } from '@/lib/content';
import { formatDate } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { Paginated } from '@/types/backoffice';
import type {
    CompositionEntry,
    ContentBrandOption,
    ContentCalendarFilters,
    ContentCampaign,
    ContentRow,
    ContentStatus,
    ContentWeek,
    PostingReminders,
} from '@/types/content';
import { Link } from '@inertiajs/vue3';
import {
    AdjustmentsHorizontalIcon,
    CalendarDaysIcon,
    ChevronDownIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ListBulletIcon,
    PencilSquareIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline';
import { computed, ref, watch } from 'vue';

/**
 * Kalender, the Content Studio workspace (ADR-0012): a week of planned posts or every post as a list, one main status
 * filter, other filters on demand, and the posts due for posting. A planned time passing changes nothing.
 */
const props = defineProps<{
    filters: ContentCalendarFilters;
    week?: ContentWeek;
    items?: ContentRow[];
    composition?: CompositionEntry[];
    list?: Paginated<ContentRow>;
    reminders: PostingReminders;
    brands: (ContentBrandOption & { id: string })[];
    manageCampaigns: boolean;
}>();

const campaignOpen = ref(false);
const campaignBrand = ref<string | null>(null);
const editingCampaign = ref<ContentCampaign | null>(null);

const { apply, applyDebounced, loading, failed } = useUrlFilters(
    () => routes.contentCalendar,
    () => ({ ...props.filters, search: props.filters.view === 'list' ? props.filters.search : null }),
    ['filters', 'week', 'items', 'composition', 'list', 'reminders'],
);

const search = ref(props.filters.search ?? '');
watch(search, (value) => {
    if (value !== (props.filters.search ?? '')) void applyDebounced({ search: value || null, page: null });
});

/** Query for a link that keeps every filter; null values are dropped so URLs stay short. */
const query = (changes: Record<string, string | null>) =>
    Object.fromEntries(Object.entries({ ...props.filters, ...changes }).filter(([, value]) => value !== null && value !== ''));
const weekQuery = (week: string) => query({ week, view: null, search: null, sort: null, direction: null });
const listQuery = computed(() => query({ view: 'list', week: null }));
const readyListHref = `${routes.contentCalendar}?view=list&status=READY&sort=planned_at&direction=asc`;

const brandName = (code: string) => props.brands.find((brand) => brand.code === code)?.name ?? code;
const pillarName = (id: string) => props.brands.flatMap((brand) => brand.pillars).find((pillar) => pillar.id === id)?.name ?? id;
const campaignName = (id: string) => props.brands.flatMap((brand) => brand.campaigns).find((campaign) => campaign.id === id)?.name ?? id;
const others = computed<ActiveFilter[]>(() =>
    [
        props.filters.view === 'list' && props.filters.search ? { key: 'search', label: `Cari: ${props.filters.search}` } : null,
        props.filters.brand ? { key: 'brand', label: `Brand: ${brandName(props.filters.brand)}` } : null,
        props.filters.pillar ? { key: 'pillar', label: `Pilar: ${pillarName(props.filters.pillar)}` } : null,
        props.filters.campaign ? { key: 'campaign', label: `Campaign: ${campaignName(props.filters.campaign)}` } : null,
        props.filters.status === 'ARCHIVED' ? { key: 'status', label: `Status: ${contentStatuses.ARCHIVED.label}` } : null,
    ].filter((filter): filter is ActiveFilter => filter !== null),
);
const moreOpen = ref(others.value.some((filter) => filter.key !== 'search'));

const week = computed(() => props.week);
const isCurrentWeek = computed(() => (week.value ? week.value.days.includes(week.value.today) : true));
const range = computed(() => (week.value ? `${formatDate(`${week.value.start}T00:00:00+07:00`)} – ${formatDate(`${week.value.end}T00:00:00+07:00`)}` : ''));
const navClass = `${buttonVariants({ variant: 'outline' })} h-11 min-w-11 border-input`;
// The view switch is the same segmented control as the status filter (DESIGN.md).
const viewClass = (active: boolean) =>
    `inline-flex items-center gap-1.5 px-4 text-sm font-bold whitespace-nowrap transition-colors duration-150 not-first:border-l not-first:border-input motion-reduce:transition-none ${
        active ? 'bg-primary-strong text-primary-foreground' : 'text-sidebar-foreground hover:bg-tint-100'
    }`;
const day = (date: string) => formatDate(`${date}T00:00:00+07:00`);

function setStatus(status: ContentStatus | null): void {
    apply({ status, page: null });
}

function remove(key: string): void {
    if (key === 'search') search.value = '';
    apply({ [key]: null, page: null });
}

function clear(): void {
    search.value = '';
    apply({ search: null, brand: null, pillar: null, campaign: null, status: props.filters.status === 'ARCHIVED' ? null : props.filters.status, page: null });
}

function sortBy(key: string): void {
    const direction = props.filters.sort === key && props.filters.direction === 'desc' ? 'asc' : 'desc';
    apply({ sort: key, direction, page: null });
}

function openCampaign(brandId: string, campaign: ContentCampaign | null): void {
    campaignBrand.value = brandId;
    editingCampaign.value = campaign;
    campaignOpen.value = true;
}
</script>

<template>
    <AppLayout title="Content Studio" description="Rencanakan, tulis, dan catat posting Instagram. Posting tetap dilakukan manual.">
        <ContentNav current="calendar" />

        <ContentReminders v-if="reminders.total > 0" :reminders="reminders" :all-href="readyListHref" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <nav aria-label="Tampilan kalender" class="inline-flex h-11 overflow-hidden rounded-md border border-input bg-card">
                <Link
                    :href="routes.contentCalendar"
                    :data="weekQuery(week?.start ?? filters.week)"
                    :class="viewClass(filters.view === 'week')"
                    :aria-current="filters.view === 'week' ? 'page' : undefined"
                    ><CalendarDaysIcon class="size-4 stroke-2" aria-hidden="true" />Minggu</Link
                >
                <Link
                    :href="routes.contentCalendar"
                    :data="listQuery"
                    :class="viewClass(filters.view === 'list')"
                    :aria-current="filters.view === 'list' ? 'page' : undefined"
                    ><ListBulletIcon class="size-4 stroke-2" aria-hidden="true" />Daftar</Link
                >
            </nav>
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    :href="routes.contentCalendar"
                    :data="query({ manage_campaigns: manageCampaigns ? null : '1' })"
                    :class="`${buttonVariants({ variant: 'outline' })} h-11 border-input`"
                    preserve-scroll
                    >{{ manageCampaigns ? 'Tutup campaign' : 'Kelola campaign' }}</Link
                >
                <ContentCreateDialog :brands="brands" :default-day="week ? (isCurrentWeek ? week.today : week.start) : null" />
            </div>
        </div>

        <PageSection v-if="manageCampaigns" title="Campaign" description="Program sementara dengan tujuan dan periode yang menjadi konteks jadwal konten.">
            <div class="space-y-5">
                <section v-for="brand in brands" :key="brand.id" class="space-y-3" :aria-labelledby="`campaign-brand-${brand.id}`">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 :id="`campaign-brand-${brand.id}`" class="text-[13px] font-extrabold">{{ brand.name }}</h3>
                        <Button class="h-11 gap-1.5" @click="openCampaign(brand.id, null)">
                            <PlusIcon class="size-3.5 stroke-2" aria-hidden="true" />Tambah campaign
                        </Button>
                    </div>
                    <EmptyState v-if="brand.campaigns.length === 0" title="Belum ada campaign" />
                    <ul v-else class="divide-y divide-border">
                        <li
                            v-for="campaign in brand.campaigns"
                            :key="campaign.id"
                            class="flex flex-wrap items-center justify-between gap-3 py-3"
                            :data-testid="`campaign-row-${campaign.name}`"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold">{{ campaign.name }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ day(campaign.startsOn) }} – {{ day(campaign.endsOn)
                                    }}<template v-if="campaign.priority !== 'NORMAL'">
                                        <SeparatorDot />prioritas {{ campaignPriorities[campaign.priority].toLowerCase() }}</template
                                    >
                                </p>
                                <p v-if="campaign.objective" class="text-sm break-words">{{ campaign.objective }}</p>
                            </div>
                            <StatusBadge :status="campaignPeriods[campaign.isActive ? campaign.period : 'off']" />
                            <Button
                                variant="outline"
                                class="h-11 gap-1.5 border-input"
                                :aria-label="`Ubah campaign ${campaign.name}`"
                                @click="openCampaign(brand.id, campaign)"
                            >
                                <PencilSquareIcon class="size-3.5 stroke-2" aria-hidden="true" />Ubah
                            </Button>
                        </li>
                    </ul>
                </section>
            </div>
            <CampaignDialog v-if="campaignBrand" v-model:open="campaignOpen" :brand-id="campaignBrand" :campaign="editingCampaign" />
        </PageSection>

        <div class="space-y-3">
            <div class="flex flex-wrap items-center gap-3">
                <ContentStatusFilter :status="filters.status === 'ARCHIVED' ? null : filters.status" @change="setStatus" />
                <div v-if="filters.view === 'list'" class="w-full md:w-72">
                    <SearchInput id="content-search" v-model="search" label="Cari judul konten" placeholder="Cari judul" />
                </div>
                <Button
                    variant="outline"
                    class="h-11 gap-1.5 border-input"
                    aria-controls="content-more-filters"
                    :aria-expanded="moreOpen"
                    @click="moreOpen = !moreOpen"
                >
                    <AdjustmentsHorizontalIcon class="size-4 stroke-2" aria-hidden="true" />Filter lainnya
                    <ChevronDownIcon
                        class="size-4 stroke-2 transition-transform motion-reduce:transition-none"
                        :class="{ 'rotate-180': moreOpen }"
                        aria-hidden="true"
                    />
                </Button>
            </div>
            <FilterBar v-show="moreOpen || others.length > 0" id="content-more-filters" :active="others" @remove="remove" @clear="clear">
                <ContentFilterFields v-if="moreOpen" :filters="filters" :brands="brands" @apply="apply({ ...$event, page: null })" />
            </FilterBar>
        </div>

        <ErrorState v-if="failed" retryable @retry="apply({})" />
        <template v-else-if="filters.view === 'list' && list">
            <PageSection flush>
                <ContentTable
                    :rows="list.data"
                    :loading="loading"
                    :failed="false"
                    :filtered="others.length > 0 || filters.status !== null"
                    :sort="filters.sort"
                    :direction="filters.direction"
                    @retry="apply({})"
                    @sort="sortBy"
                />
                <TablePagination :meta="list.meta" :disabled="loading" @change="apply({ page: String($event) })" />
            </PageSection>
        </template>
        <template v-else-if="week && items">
            <div class="flex flex-wrap items-center gap-2">
                <Link :href="routes.contentCalendar" :data="weekQuery(week.previous)" :class="navClass" aria-label="Minggu sebelumnya" preserve-scroll
                    ><ChevronLeftIcon class="size-4 stroke-2" aria-hidden="true"
                /></Link>
                <h2 class="min-w-52 text-center text-sm font-extrabold tabular" aria-live="polite">{{ range }}</h2>
                <Link :href="routes.contentCalendar" :data="weekQuery(week.next)" :class="navClass" aria-label="Minggu berikutnya" preserve-scroll
                    ><ChevronRightIcon class="size-4 stroke-2" aria-hidden="true"
                /></Link>
                <Link v-if="!isCurrentWeek" :href="routes.contentCalendar" :data="weekQuery(week.today)" :class="navClass">Minggu ini</Link>
            </div>
            <ContentComposition v-if="composition" :entries="composition" />
            <ContentWeekView :week="week" :items="items" :loading="loading" />
        </template>
    </AppLayout>
</template>
