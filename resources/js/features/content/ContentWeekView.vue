<script setup lang="ts">
import { contentStatuses, formatTime, formatWeekday } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { StatusTone } from '@/lib/status';
import type { ContentRow, ContentWeek } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { PhotoIcon } from '@heroicons/vue/24/outline';
import { computed } from 'vue';

/**
 * One week (DESIGN.md): seven columns from xl, one or two below. Each day is a column under a tint-100 band, today's
 * band carries the live-orange rule, and each post is a row: time, title, and its status as a word in its tone.
 */
const props = defineProps<{ week: ContentWeek; items: ContentRow[]; loading?: boolean }>();

const statusText: Record<StatusTone, string> = {
    success: 'text-success',
    warning: 'text-warning',
    danger: 'text-danger',
    info: 'text-info',
    neutral: 'text-sidebar-foreground',
};

const byDay = computed(() => props.week.days.map((day) => ({ day, items: props.items.filter((item) => item.plannedDay === day) })));
</script>

<template>
    <ol
        class="grid gap-x-3 gap-y-5 md:grid-cols-2 xl:grid-cols-7"
        :aria-busy="loading"
        aria-label="Konten minggu ini per hari"
        :class="{ 'opacity-60': loading }"
    >
        <li v-for="{ day, items: dayItems } in byDay" :key="day" class="flex min-w-0 flex-col" :data-day="day">
            <h3
                class="flex h-10 items-center justify-between gap-2 rounded-[10px] px-3 text-[11px] font-extrabold tracking-[0.07em] uppercase"
                :class="day === week.today ? 'border-t-[3px] border-primary bg-tint-100 text-foreground' : 'bg-tint-100 text-sidebar-foreground'"
            >
                <span class="xl:hidden">{{ formatWeekday(day) }}</span>
                <span class="hidden xl:inline">{{ formatWeekday(day, true) }}</span>
                <span v-if="day === week.today" class="tracking-normal normal-case">Hari ini</span>
            </h3>
            <ul v-if="dayItems.length" class="divide-y divide-border">
                <li v-for="item in dayItems" :key="item.id">
                    <Link
                        :href="routes.contentItem(item.id)"
                        class="block space-y-1 rounded-md px-2 py-3 transition-colors hover:bg-tint-50 motion-reduce:transition-none"
                    >
                        <span class="flex items-center justify-between gap-2 text-xs text-muted-foreground tabular">
                            <time :datetime="item.plannedAt">{{ formatTime(item.plannedAt) }}</time>
                            <span v-if="item.hasImage" class="inline-flex"
                                ><PhotoIcon class="size-3.5 stroke-2" aria-hidden="true" /><span class="sr-only">Ada gambar</span></span
                            >
                        </span>
                        <span class="line-clamp-4 block text-sm font-semibold hyphens-auto" lang="id">{{ item.title }}</span>
                        <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                            <span class="font-bold" :class="statusText[contentStatuses[item.status].tone]">{{ contentStatuses[item.status].label }}</span>
                            <span v-if="item.dueState === 'overdue'" class="font-semibold text-danger">Terlambat diposting</span>
                            <span v-else-if="item.dueState === 'today'" class="font-semibold text-warning">Jadwal hari ini</span>
                            <span v-if="!item.hasImage" class="text-muted-foreground">Tanpa gambar</span>
                        </span>
                    </Link>
                </li>
            </ul>
            <p v-else class="px-2 py-3 text-xs text-muted-foreground">Belum ada konten</p>
        </li>
    </ol>
</template>
