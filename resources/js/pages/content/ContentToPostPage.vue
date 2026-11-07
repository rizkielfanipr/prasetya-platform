<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import ContentNav from '@/features/content/ContentNav.vue';
import { dueLabel } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { ToPostRow } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, ImageIcon } from '@lucide/vue';
import { computed } from 'vue';

/**
 * Siap posting (ADR-0011, phase 7): approved posts to post by hand, overdue first. A reminder only; a post becomes
 * published when someone marks it, never because its time passed.
 */
const props = defineProps<{ overdue: ToPostRow[]; today: ToPostRow[]; upcoming: ToPostRow[]; upcomingDays: number; later: number }>();

const groups = computed(() => [
    { key: 'overdue', title: 'Terlambat', description: 'Rencananya sudah lewat dan belum ditandai diposting.', rows: props.overdue, tone: 'text-danger' },
    { key: 'today', title: 'Hari ini', description: 'Jadwal posting hari ini (WIB).', rows: props.today, tone: 'text-warning' },
    {
        key: 'upcoming',
        title: `${props.upcomingDays} hari ke depan`,
        description: 'Sudah disetujui, siap saat jadwalnya tiba.',
        rows: props.upcoming,
        tone: 'text-muted-foreground',
    },
]);
const nothing = computed(() => props.overdue.length + props.today.length + props.upcoming.length === 0);
</script>

<template>
    <AppLayout title="Content Studio" description="Konten yang sudah disetujui dan menunggu diposting manual.">
        <ContentNav current="to-post" />

        <PanelCard v-if="nothing">
            <EmptyState
                :icon="CheckCircle2"
                title="Tidak ada yang menunggu diposting"
                description="Konten yang disetujui akan muncul di sini sampai ditandai sudah dipublikasikan."
            />
        </PanelCard>

        <template v-else>
            <PanelCard v-for="group in groups" :key="group.key" :title="`${group.title} (${group.rows.length})`" :description="group.description">
                <p v-if="group.rows.length === 0" class="text-sm text-muted-foreground">Tidak ada.</p>
                <ul v-else class="divide-y rounded-lg border" :data-testid="`to-post-${group.key}`">
                    <li v-for="row in group.rows" :key="row.id" class="flex flex-wrap items-center justify-between gap-3 p-3">
                        <div class="min-w-0 flex-1">
                            <Link :href="routes.contentItem(row.id)" class="font-semibold break-words hover:underline">{{ row.title }}</Link>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDateTime(row.plannedAt) }}<template v-if="row.pillar"> · {{ row.pillar.name }}</template
                                ><template v-if="!row.hasImage"> · belum ada gambar</template>
                            </p>
                        </div>
                        <span v-if="dueLabel(row.dueState, row.daysLate)" class="text-xs font-semibold" :class="group.tone">{{
                            dueLabel(row.dueState, row.daysLate)
                        }}</span>
                        <Link
                            :href="`${routes.contentItem(row.id)}#paket-posting`"
                            class="inline-flex h-9 items-center gap-1.5 rounded-md border bg-background px-3 text-sm font-medium hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            ><ImageIcon class="size-4" aria-hidden="true" />Buka paket posting</Link
                        >
                    </li>
                </ul>
            </PanelCard>
            <p v-if="later > 0" class="text-sm text-muted-foreground">{{ later }} konten lain disetujui untuk jadwal yang lebih jauh.</p>
        </template>
    </AppLayout>
</template>
