<script setup lang="ts">
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import { dueLabel } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { PostingReminders } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { BellAlertIcon, PhotoIcon } from '@heroicons/vue/24/outline';

/**
 * Ready posts due today or earlier, on top of the Kalender whatever week is shown. A reminder only: a post becomes
 * published when someone marks it, never because its time passed (ADR-0012).
 */
defineProps<{ reminders: PostingReminders; allHref: string }>();
</script>

<template>
    <section class="rounded-xl bg-warning-surface px-5 py-4" aria-labelledby="posting-reminders" data-testid="posting-reminders">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="posting-reminders" class="flex items-center gap-2 text-sm font-extrabold text-warning">
                <BellAlertIcon class="size-4 stroke-2 text-warning" aria-hidden="true" />Perlu diposting ({{ reminders.total }})
            </h2>
            <Link :href="allHref" class="text-sm font-bold text-primary-text underline decoration-2 underline-offset-4">Lihat semua yang Ready</Link>
        </div>
        <ul class="mt-2 divide-y divide-warning/20">
            <li v-for="row in reminders.items" :key="row.id" class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 py-2.5">
                <div class="min-w-0 flex-1">
                    <Link :href="routes.contentItem(row.id)" class="text-sm font-bold break-words hover:underline">{{ row.title }}</Link>
                    <p class="text-xs text-foreground tabular">
                        {{ formatDateTime(row.plannedAt) }}<template v-if="!row.hasImage"><SeparatorDot />belum ada gambar</template>
                    </p>
                </div>
                <span class="text-xs font-semibold" :class="row.dueState === 'overdue' ? 'text-danger' : 'text-warning'">{{
                    dueLabel(row.dueState, row.daysLate)
                }}</span>
                <Link
                    :href="`${routes.contentItem(row.id)}#paket-posting`"
                    class="inline-flex h-11 items-center gap-1.5 rounded-md border border-input bg-card px-3 text-sm font-bold hover:bg-tint-100"
                    ><PhotoIcon class="size-4 stroke-2" aria-hidden="true" />Buka paket posting</Link
                >
            </li>
        </ul>
        <p v-if="reminders.total > reminders.items.length" class="mt-2 text-xs text-foreground">
            {{ reminders.total - reminders.items.length }} lainnya ada di daftar Ready.
        </p>
    </section>
</template>
