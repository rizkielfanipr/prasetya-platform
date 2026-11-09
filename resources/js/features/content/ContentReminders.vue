<script setup lang="ts">
import { dueLabel } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { PostingReminders } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { BellRing, ImageIcon } from '@lucide/vue';

/**
 * Ready posts due today or earlier, on top of the Kalender whatever week is shown. A reminder only: a post becomes
 * published when someone marks it, never because its time passed (ADR-0012).
 */
defineProps<{ reminders: PostingReminders; allHref: string }>();
</script>

<template>
    <section class="rounded-xl border border-warning/30 bg-warning-surface p-4" aria-labelledby="posting-reminders" data-testid="posting-reminders">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="posting-reminders" class="flex items-center gap-2 text-sm font-semibold">
                <BellRing class="size-4 text-warning" aria-hidden="true" />Perlu diposting ({{ reminders.total }})
            </h2>
            <Link :href="allHref" class="text-sm font-medium text-primary-text hover:underline">Lihat semua yang siap posting</Link>
        </div>
        <ul class="mt-3 divide-y rounded-lg border bg-card">
            <li v-for="row in reminders.items" :key="row.id" class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 p-3">
                <div class="min-w-0 flex-1">
                    <Link :href="routes.contentItem(row.id)" class="font-semibold break-words hover:underline">{{ row.title }}</Link>
                    <p class="text-xs text-muted-foreground">
                        {{ formatDateTime(row.plannedAt) }}<template v-if="!row.hasImage"> · belum ada gambar</template>
                    </p>
                </div>
                <span class="text-xs font-semibold" :class="row.dueState === 'overdue' ? 'text-danger' : 'text-warning'">{{
                    dueLabel(row.dueState, row.daysLate)
                }}</span>
                <Link
                    :href="`${routes.contentItem(row.id)}#paket-posting`"
                    class="inline-flex h-9 items-center gap-1.5 rounded-md border bg-background px-3 text-sm font-medium hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    ><ImageIcon class="size-4" aria-hidden="true" />Buka paket posting</Link
                >
            </li>
        </ul>
        <p v-if="reminders.total > reminders.items.length" class="mt-2 text-xs text-muted-foreground">
            {{ reminders.total - reminders.items.length }} lainnya ada di daftar Siap posting.
        </p>
    </section>
</template>
