<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PageSection from '@/components/layout/PageSection.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import SelectFilter from '@/components/shared/SelectFilter.vue';
import { useUrlFilters } from '@/composables/useUrlFilters';
import ContentNav from '@/features/content/ContentNav.vue';
import { formatRate } from '@/lib/content';
import { formatDateTime, formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ContentBrand, InsightGroup, PerformanceSummary } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { ChartBarIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

/**
 * Insight (ADR-0011, phase 8): how published posts performed, from each post's latest manual reading. For people (and
 * later the strategist) to learn from; nothing here changes rules, pillars, or prompts.
 */
const props = defineProps<{ filters: { days: number; brand: string | null }; windows: number[]; brands: ContentBrand[]; summary: PerformanceSummary }>();

const { apply } = useUrlFilters(
    () => routes.contentInsights,
    () => ({ days: String(props.filters.days), brand: props.filters.brand }),
    ['filters', 'summary'],
);
const windowOptions = computed(() => props.windows.map((d) => ({ value: String(d), label: `${d} hari terakhir` })));
const brandOptions = computed(() => props.brands.map((b) => ({ value: b.code, label: b.name })));
const tables = computed<{ key: string; title: string; rows: InsightGroup[] }[]>(() => [
    { key: 'pillar', title: 'Per pilar', rows: props.summary.byPillar },
    { key: 'campaign', title: 'Per campaign', rows: props.summary.byCampaign },
    { key: 'topic', title: 'Per topik (10 teratas)', rows: props.summary.byTopic },
]);
const t = computed(() => props.summary.totals);
</script>

<template>
    <AppLayout title="Content Studio" description="Performa posting dari angka Insight Instagram yang dicatat manual.">
        <ContentNav current="insights" />

        <section aria-label="Filter" class="flex flex-wrap items-end gap-3">
            <SelectFilter
                id="insight-window"
                label="Periode"
                all-label="90 hari terakhir"
                :options="windowOptions.filter((o) => o.value !== '90')"
                :model-value="filters.days === 90 ? null : String(filters.days)"
                @update:model-value="apply({ days: $event ?? '90' })"
            />
            <SelectFilter
                v-if="brands.length > 1"
                id="insight-brand"
                label="Brand"
                all-label="Semua brand"
                :options="brandOptions"
                :model-value="filters.brand"
                @update:model-value="apply({ brand: $event })"
            />
        </section>

        <PageSection v-if="t.posts === 0">
            <EmptyState
                :icon="ChartBarIcon"
                title="Belum ada posting di periode ini"
                description="Insight muncul setelah konten ditandai sudah diposting dan angkanya dicatat."
            />
        </PageSection>
        <template v-else>
            <!-- One line of totals on a tint field, figures ranked by weight, not tiles (DESIGN.md). -->
            <p class="rounded-xl bg-tint-50 px-5 py-4 text-sm leading-7 md:px-7" data-testid="insight-totals">
                <b class="font-extrabold tabular">{{ t.measured }} dari {{ t.posts }}</b> posting sudah dicatat insight-nya<SeparatorDot />rata-rata jangkauan
                <b class="font-extrabold tabular">{{ t.avgReach === null ? '—' : formatNumber(t.avgReach) }}</b
                ><SeparatorDot />rata-rata engagement <b class="font-extrabold tabular">{{ formatRate(t.avgEngagement) }}</b
                ><SeparatorDot /><b class="font-extrabold tabular">{{ formatNumber(t.saves) }}</b>
                kali disimpan
            </p>

            <PageSection v-if="summary.missing.length" title="Belum dicatat insight-nya" description="Diposting lebih dari 2 hari lalu tanpa angka Insight.">
                <ul class="space-y-1 text-sm" data-testid="insight-missing">
                    <li v-for="row in summary.missing" :key="row.id">
                        <Link :href="`${routes.contentItem(row.id)}#insight`" class="font-bold hover:underline">{{ row.title }}</Link>
                        <span class="text-xs text-muted-foreground tabular"><SeparatorDot />{{ formatDateTime(row.publishedAt) }}</span>
                    </li>
                </ul>
            </PageSection>

            <div class="grid gap-4 lg:grid-cols-3">
                <PageSection v-for="table in tables" :key="table.key" :title="table.title">
                    <p v-if="table.rows.length === 0" class="text-sm text-muted-foreground">Belum ada data.</p>
                    <table v-else class="w-full text-sm" :data-testid="`insight-${table.key}`">
                        <caption class="sr-only">
                            {{
                                table.title
                            }}
                        </caption>
                        <thead>
                            <tr class="bg-tint-100 text-left text-[11px] font-extrabold tracking-[0.07em] text-sidebar-foreground uppercase">
                                <th scope="col" class="h-10 rounded-l-[10px] px-2">Nama</th>
                                <th scope="col" class="h-10 px-2 text-right">Posting</th>
                                <th scope="col" class="h-10 px-2 text-right">Jangkauan</th>
                                <th scope="col" class="h-10 rounded-r-[10px] px-2 text-right">Engagement</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <tr v-for="row in table.rows" :key="row.name">
                                <th scope="row" class="px-2 py-2.5 text-left font-semibold break-words">{{ row.name }}</th>
                                <td class="px-2 py-2.5 text-right tabular">{{ row.posts }}</td>
                                <td class="px-2 py-2.5 text-right tabular">{{ row.avgReach === null ? '—' : formatNumber(row.avgReach) }}</td>
                                <td class="px-2 py-2.5 text-right tabular">{{ formatRate(row.avgEngagement) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </PageSection>
            </div>

            <PageSection title="Posting terbaik" description="Lima engagement tertinggi, dengan arahan visualnya untuk dibandingkan.">
                <p v-if="summary.top.length === 0" class="text-sm text-muted-foreground">Belum ada insight yang dicatat.</p>
                <ol v-else class="divide-y divide-border" data-testid="insight-top">
                    <li v-for="post in summary.top" :key="post.id" class="py-3 first:pt-0">
                        <p class="flex flex-wrap items-baseline justify-between gap-2">
                            <Link :href="routes.contentItem(post.id)" class="text-sm font-bold break-words hover:underline">{{ post.title }}</Link>
                            <span class="text-sm tabular">{{ formatRate(post.engagement) }}<SeparatorDot />jangkauan {{ formatNumber(post.reach) }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ [post.pillar, post.topic].filter(Boolean).join(', ') || 'Tanpa pilar' }}<SeparatorDot />{{ formatDateTime(post.publishedAt) }}
                        </p>
                        <p v-if="post.visualDirection" class="mt-1 text-sm break-words text-muted-foreground">Visual: {{ post.visualDirection }}</p>
                    </li>
                </ol>
            </PageSection>
        </template>
    </AppLayout>
</template>
