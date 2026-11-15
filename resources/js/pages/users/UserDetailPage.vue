<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import AuditTimeline from '@/components/shared/AuditTimeline.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import UserRowActions from '@/features/users/UserRowActions.vue';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import { userStatuses } from '@/lib/status';
import type { AuditEntry, UserRow } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';

// A user and their activity (ADR-0010). The audit never carries a password, hash, or token.
defineProps<{ user: UserRow; activity: AuditEntry[] }>();
</script>

<template>
    <AppLayout :title="user.name" :description="`@${user.username}`">
        <Link
            :href="routes.users"
            class="inline-flex items-center gap-1.5 text-[13px] font-semibold text-primary-text underline decoration-tint-300 underline-offset-4 hover:decoration-tint-600"
        >
            <ArrowLeftIcon class="size-4 stroke-2" aria-hidden="true" />Kembali ke pengguna
        </Link>

        <section aria-label="Detail akun" class="rounded-xl bg-tint-50 p-5 md:p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Akun</p>
                    <p class="text-sign-ink mt-1 font-mono text-[14px] font-semibold">
                        @{{ user.username }}<span v-if="user.isSelf" class="ml-2 font-sans text-[12px] text-muted-foreground">Akun Anda</span>
                    </p>
                </div>
                <UserRowActions :user="user" />
            </div>

            <dl class="border-hairline mt-5 grid gap-x-6 gap-y-4 border-t pt-4 sm:grid-cols-3">
                <div>
                    <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Status</dt>
                    <dd class="mt-1.5"><StatusBadge :status="user.isActive ? userStatuses.active : userStatuses.inactive" /></dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Masuk terakhir</dt>
                    <dd class="text-sign-ink mt-1.5 text-[14px]">{{ formatDateTime(user.lastLoginAt) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Dibuat</dt>
                    <dd class="text-sign-ink mt-1.5 text-[14px]">{{ formatDateTime(user.createdAt) }}</dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="activity-heading" class="border-t border-border pt-4">
            <h2 id="activity-heading" class="text-[14px] font-extrabold">Aktivitas</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">Perubahan akun ini, masuk, dan keluar, terbaru di atas (maksimal 100 catatan).</p>
            <div class="mt-4"><AuditTimeline :entries="activity" /></div>
        </section>
    </AppLayout>
</template>
