<script setup lang="ts">
import { brands } from '@/lib/brands';
import { copy } from '@/lib/copy';
import { routes } from '@/lib/routes';
import { formatNumber } from '@/lib/format';
import type { SharedProps } from '@/types/backoffice';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { LayoutDashboard, type LucideIcon, Megaphone, ReceiptText, Settings, Users } from '@lucide/vue';

defineEmits<{ navigate: [] }>();

const page = usePage<SharedProps>();
// Issues needing action, shown on Transaksi (ADR-0010). The cached count never includes duplicate webhooks.
const openIssues = computed(() => page.props.openIssueCount ?? 0);
// One access level (ADR-0009): every signed-in user sees every item.
const items: { label: string; href: string; icon: LucideIcon; section?: string }[] = [
    { label: 'Dashboard', href: routes.dashboard, icon: LayoutDashboard },
    { label: 'Transaksi', href: routes.transactions, icon: ReceiptText },
    { label: 'Content Studio', href: routes.contentCalendar, icon: Megaphone, section: routes.content },
    { label: 'Pengguna', href: routes.users, icon: Users },
    { label: 'Pengaturan', href: routes.settings, icon: Settings },
];

const isActive = (href: string): boolean => page.url === href || page.url.startsWith(`${href}/`) || page.url.startsWith(`${href}?`);
const itemActive = (item: { href: string; section?: string }): boolean => isActive(item.section ?? item.href);
</script>

<template>
    <div class="flex h-full flex-col bg-sidebar text-sidebar-foreground">
        <Link :href="routes.dashboard" class="flex items-center gap-3 px-5 py-5" @click="$emit('navigate')">
            <img src="/images/brands/platform-mark.svg" alt="" width="36" height="36" class="size-9" />
            <span class="font-heading text-lg leading-tight font-bold text-white">Prasetya<br />Platform</span>
        </Link>

        <nav aria-label="Navigasi utama" class="flex-1 space-y-1 overflow-y-auto px-3 py-2">
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                class="flex h-12 items-center gap-3 rounded-md px-3 text-sm font-medium transition-colors duration-150 hover:bg-sidebar-active hover:text-white"
                :class="itemActive(item) ? 'bg-sidebar-active font-semibold text-white' : ''"
                :aria-current="itemActive(item) ? 'page' : undefined"
                @click="$emit('navigate')"
            >
                <component :is="item.icon" class="size-5 shrink-0" :class="itemActive(item) ? 'text-primary' : ''" aria-hidden="true" />
                <span class="flex-1">{{ item.label }}</span>
                <span
                    v-if="item.href === routes.transactions && openIssues > 0"
                    class="rounded-full bg-primary-strong px-2 py-0.5 text-[11px] font-bold text-white tabular"
                    :aria-label="`${openIssues} isu perlu ditinjau`"
                    data-testid="sidebar-issue-badge"
                    >{{ formatNumber(openIssues) }}</span
                >
            </Link>
        </nav>

        <div class="border-t border-sidebar-border px-5 py-4">
            <p class="mb-3 text-xs text-sidebar-muted">{{ copy.managedFor }}</p>
            <ul class="space-y-3">
                <li v-for="brand in brands" :key="brand.label">
                    <img :src="brand.logo.src" :alt="brand.fullName" :width="brand.logo.width" :height="brand.logo.height" class="h-8 w-auto" />
                </li>
            </ul>
        </div>
    </div>
</template>
