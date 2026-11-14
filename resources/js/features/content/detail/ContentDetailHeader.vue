<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import ContentStatusBadge from '@/features/content/ContentStatusBadge.vue';
import { detailSurfaceLabels, dueLabel } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { ContentActions, ContentItem, DetailSurface } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { Archive, ArrowLeft, BookOpen, CalendarClock, Clock, History, ListChecks, MoreHorizontal, Palette, Undo2 } from '@lucide/vue';
import { type Component, computed } from 'vue';

/**
 * The shared header of every status (ADR-0013): back to the item's week, the status and due reminder in words, and
 * Lainnya for the rare or destructive actions. A menu entry exists only when the server allows the action now; the
 * server still checks every request. The title, brand, and schedule are the page heading above (AppHeader).
 */
const props = defineProps<{ item: ContentItem; actions: ContentActions; editing: boolean }>();
const emit = defineEmits<{ open: [surface: DetailSurface] }>();

const calendarHref = computed(() => `${routes.contentCalendar}?week=${props.item.plannedDay}`);
const due = computed(() => dueLabel(props.item.dueState, props.item.daysLate ?? 0));

interface Entry {
    surface: DetailSurface;
    icon: Component;
}
const entries = computed(() => {
    const { status } = props.item;
    const draftLike = status === 'DRAFT' || props.editing;
    const routine: Entry[] = [];
    if (props.actions.editDetails === null) routine.push({ surface: 'plan', icon: CalendarClock });
    if (draftLike && props.actions.edit === null) routine.push({ surface: 'creative', icon: Palette });
    routine.push({ surface: 'research', icon: BookOpen }, { surface: 'history', icon: History }, { surface: 'activity', icon: ListChecks });

    const lifecycle: Entry[] = [];
    if (status === 'READY' && props.actions.returnToDraft === null) lifecycle.push({ surface: 'returnToDraft', icon: Undo2 });
    if (props.actions.archive === null) lifecycle.push({ surface: 'archive', icon: Archive });

    return { routine, lifecycle };
});

// The surface opens once the menu has closed and handed focus back to Lainnya, so closing the surface returns there.
let pending: DetailSurface | null = null;
function choose(surface: DetailSurface): void {
    pending = surface;
}
function afterMenuClosed(): void {
    const surface = pending;
    pending = null;
    if (surface) window.requestAnimationFrame(() => emit('open', surface));
}
</script>

<template>
    <!-- On phones the page heading has room for a few words only; the full title repeats here for sight, not for screen readers. -->
    <p aria-hidden="true" class="text-lg leading-snug font-bold break-words md:hidden" data-testid="detail-title">{{ item.title }}</p>
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2" data-testid="detail-header">
        <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1">
            <Link :href="calendarHref" class="inline-flex min-h-11 items-center gap-1.5 text-sm font-semibold text-primary-text hover:underline">
                <ArrowLeft class="size-4" aria-hidden="true" />Kembali ke Kalender
            </Link>
            <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm" data-testid="detail-status">
                <span class="sr-only">Status:</span>
                <ContentStatusBadge :status="item.status" />
                <span
                    v-if="due"
                    class="inline-flex items-center gap-1.5 font-semibold"
                    :class="item.dueState === 'overdue' ? 'text-danger' : 'text-warning'"
                    data-testid="detail-due"
                >
                    <Clock class="size-4" aria-hidden="true" />{{ due }}
                </span>
                <span v-if="item.campaign" class="text-muted-foreground">Campaign {{ item.campaign.name }}</span>
            </p>
        </div>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="outline" class="h-11 gap-2" data-testid="detail-menu"> <MoreHorizontal class="size-4" aria-hidden="true" />Lainnya </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-60" @close-auto-focus="afterMenuClosed">
                <DropdownMenuItem v-for="entry in entries.routine" :key="entry.surface" class="min-h-11 cursor-pointer" @select="choose(entry.surface)">
                    <component :is="entry.icon" aria-hidden="true" />{{ detailSurfaceLabels[entry.surface] }}
                </DropdownMenuItem>
                <template v-if="entries.lifecycle.length">
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        v-for="entry in entries.lifecycle"
                        :key="entry.surface"
                        class="min-h-11 cursor-pointer"
                        :variant="entry.surface === 'archive' ? 'destructive' : 'default'"
                        @select="choose(entry.surface)"
                    >
                        <component :is="entry.icon" aria-hidden="true" />{{ detailSurfaceLabels[entry.surface] }}
                    </DropdownMenuItem>
                </template>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
