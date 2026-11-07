<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import MetricCard from '@/components/shared/MetricCard.vue';
import SelectFilter from '@/components/shared/SelectFilter.vue';
import { useUrlFilters } from '@/composables/useUrlFilters';
import ContentNav from '@/features/content/ContentNav.vue';
import { formatRate } from '@/lib/content';
import { formatDateTime, formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ContentBrand, InsightGroup, PerformanceSummary } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { BarChart3, Bookmark, ClipboardCheck, Eye, Heart } from '@lucide/vue';
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
    { key: 'campaign', title: 'Per kampanye', rows: props.summary.byCampaign },
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

        <PanelCard v-if="t.posts === 0">
            <EmptyState
                :icon="BarChart3"
                title="Belum ada posting di periode ini"
                description="Insight muncul setelah konten ditandai dipublikasikan dan angkanya dicatat."
            />
        </PanelCard>
        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-testid="insight-totals">
                <MetricCard label="Posting tercatat" :value="`${t.measured} dari ${t.posts}`" :icon="ClipboardCheck" tone="primary" />
                <MetricCard label="Rata-rata jangkauan" :value="t.avgReach === null ? '—' : formatNumber(t.avgReach)" :icon="Eye" tone="primary" />
                <MetricCard label="Rata-rata engagement" :value="formatRate(t.avgEngagement)" :icon="Heart" tone="success" />
                <MetricCard label="Total simpan" :value="formatNumber(t.saves)" :icon="Bookmark" tone="warning" />
            </div>

            <PanelCard v-if="summary.missing.length" title="Belum dicatat insight-nya" description="Dipublikasikan lebih dari 2 hari lalu tanpa angka Insight.">
                <ul class="space-y-1 text-sm" data-testid="insight-missing">
                    <li v-for="row in summary.missing" :key="row.id">
                        <Link :href="`${routes.contentItem(row.id)}#insight`" class="font-medium hover:underline">{{ row.title }}</Link>
                        <span class="text-xs text-muted-foreground"> · {{ formatDateTime(row.publishedAt) }}</span>
                    </li>
                </ul>
            </PanelCard>

            <div class="grid gap-4 lg:grid-cols-3">
                <PanelCard v-for="table in tables" :key="table.key" :title="table.title">
                    <p v-if="table.rows.length === 0" class="text-sm text-muted-foreground">Belum ada data.</p>
                    <table v-else class="w-full text-sm" :data-testid="`insight-${table.key}`">
                        <caption class="sr-only">
                            {{
                                table.title
                            }}
                        </caption>
                        <thead>
                            <tr class="text-left text-xs text-muted-foreground">
                                <th scope="col" class="pb-2 font-semibold">Nama</th>
                                <th scope="col" class="pb-2 text-right font-semibold">Posting</th>
                                <th scope="col" class="pb-2 text-right font-semibold">Jangkauan</th>
                                <th scope="col" class="pb-2 text-right font-semibold">Engagement</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="row in table.rows" :key="row.name">
                                <th scope="row" class="py-2 text-left font-medium break-words">{{ row.name }}</th>
                                <td class="py-2 text-right tabular">{{ row.posts }}</td>
                                <td class="py-2 text-right tabular">{{ row.avgReach === null ? '—' : formatNumber(row.avgReach) }}</td>
                                <td class="py-2 text-right tabular">{{ formatRate(row.avgEngagement) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </PanelCard>
            </div>

            <PanelCard title="Posting terbaik" description="Lima engagement tertinggi, dengan arahan visualnya untuk dibandingkan.">
                <p v-if="summary.top.length === 0" class="text-sm text-muted-foreground">Belum ada insight yang dicatat.</p>
                <ol v-else class="space-y-3" data-testid="insight-top">
                    <li v-for="post in summary.top" :key="post.id" class="rounded-lg border p-3">
                        <p class="flex flex-wrap items-baseline justify-between gap-2">
                            <Link :href="routes.contentItem(post.id)" class="font-semibold break-words hover:underline">{{ post.title }}</Link>
                            <span class="text-sm tabular">{{ formatRate(post.engagement) }} · jangkauan {{ formatNumber(post.reach) }}</span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ [post.pillar, post.topic].filter(Boolean).join(' · ') || 'Tanpa pilar' }} · {{ formatDateTime(post.publishedAt) }}
                        </p>
                        <p v-if="post.visualDirection" class="mt-1 text-sm break-words text-muted-foreground">Visual: {{ post.visualDirection }}</p>
                    </li>
                </ol>
            </PanelCard>
        </template>
    </AppLayout>
</template>
