<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import AuditTimeline from '@/components/shared/AuditTimeline.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import UserRowActions from '@/features/users/UserRowActions.vue';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import { userStatuses } from '@/lib/status';
import type { AuditEntry, UserRow } from '@/types/backoffice';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';

// A user and their activity (ADR-0010). The audit never carries a password, hash, or token.
defineProps<{ user: UserRow; activity: AuditEntry[] }>();
</script>

<template>
    <AppLayout :title="user.name" :description="`@${user.username}`">
        <Link :href="routes.users" class="inline-flex items-center gap-1.5 text-sm font-medium text-primary-text hover:underline">
            <ArrowLeft class="size-4" aria-hidden="true" />Kembali ke pengguna
        </Link>

        <PanelCard title="Pengguna">
            <template #actions><UserRowActions :user="user" /></template>
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Username</dt>
                    <dd class="mt-1 font-mono text-sm">
                        {{ user.username }}<span v-if="user.isSelf" class="ml-1 font-sans text-xs text-muted-foreground">(Anda)</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Status</dt>
                    <dd class="mt-1"><StatusBadge :status="user.isActive ? userStatuses.active : userStatuses.inactive" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Masuk terakhir</dt>
                    <dd class="mt-1">{{ formatDateTime(user.lastLoginAt) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Dibuat</dt>
                    <dd class="mt-1">{{ formatDateTime(user.createdAt) }}</dd>
                </div>
            </dl>
        </PanelCard>

        <PanelCard title="Aktivitas" description="Perubahan akun ini, masuk, dan keluar, terbaru di atas (maksimal 100 catatan).">
            <AuditTimeline :entries="activity" />
        </PanelCard>
    </AppLayout>
</template>
