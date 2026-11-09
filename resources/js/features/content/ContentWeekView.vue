<script setup lang="ts">
import { contentStatuses, formatTime, formatWeekday } from '@/lib/content';
import { toneClasses } from '@/lib/status';
import { routes } from '@/lib/routes';
import type { ContentRow, ContentWeek } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { ImageIcon } from '@lucide/vue';
import { computed } from 'vue';

/** One week: seven columns from xl, days as cards in one or two columns below it. */
const props = defineProps<{ week: ContentWeek; items: ContentRow[]; loading?: boolean }>();

const byDay = computed(() => props.week.days.map((day) => ({ day, items: props.items.filter((item) => item.plannedDay === day) })));
</script>

<template>
    <ol class="grid gap-3 md:grid-cols-2 xl:grid-cols-7" :aria-busy="loading" aria-label="Konten minggu ini per hari" :class="{ 'opacity-60': loading }">
        <li v-for="{ day, items: dayItems } in byDay" :key="day" class="flex min-w-0 flex-col rounded-xl border bg-card" :data-day="day">
            <h3
                class="flex items-baseline justify-between gap-2 border-b px-3 py-2 text-sm font-semibold"
                :class="day === week.today ? 'text-primary-text' : ''"
            >
                <span class="xl:hidden">{{ formatWeekday(day) }}</span>
                <span class="hidden xl:inline">{{ formatWeekday(day, true) }}</span>
                <span v-if="day === week.today" class="text-xs font-semibold">Hari ini</span>
            </h3>
            <ul v-if="dayItems.length" class="space-y-2 p-2 xl:p-1.5">
                <li v-for="item in dayItems" :key="item.id">
                    <Link
                        :href="routes.contentItem(item.id)"
                        class="block space-y-2 rounded-lg border bg-background p-3 transition-colors hover:border-primary-strong focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none xl:p-2.5"
                    >
                        <span class="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                            <time :datetime="item.plannedAt">{{ formatTime(item.plannedAt) }}</time>
                            <span v-if="item.hasImage" class="inline-flex"
                                ><ImageIcon class="size-3.5" aria-hidden="true" /><span class="sr-only">Ada gambar</span></span
                            >
                        </span>
                        <span class="line-clamp-4 block text-sm font-semibold hyphens-auto" lang="id">{{ item.title }}</span>
                        <span v-if="!item.hasImage" class="block text-xs text-muted-foreground">Tanpa gambar</span>
                        <span v-if="item.dueState === 'overdue'" class="block text-xs font-semibold text-danger">Terlambat diposting</span>
                        <span v-else-if="item.dueState === 'today'" class="block text-xs font-semibold text-warning">Jadwal hari ini</span>
                        <!-- Wraps instead of overflowing in a narrow day column. -->
                        <span
                            class="inline-flex max-w-full items-start gap-1 rounded-md px-2 py-0.5 text-xs font-semibold xl:px-1.5 xl:text-[11px] 2xl:text-xs"
                            :class="toneClasses[contentStatuses[item.status].tone]"
                        >
                            <component :is="contentStatuses[item.status].icon" class="mt-0.5 size-3.5 shrink-0 xl:hidden 2xl:inline" aria-hidden="true" />{{
                                contentStatuses[item.status].label
                            }}
                        </span>
                    </Link>
                </li>
            </ul>
            <p v-else class="px-3 py-3 text-xs text-muted-foreground">Belum ada konten</p>
        </li>
    </ol>
</template>
