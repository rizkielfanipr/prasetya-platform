<script setup lang="ts">
import { brands } from '@/lib/brands';
import { copy } from '@/lib/copy';
import { routes } from '@/lib/routes';
import { formatNumber } from '@/lib/format';
import type { SharedProps } from '@/types/backoffice';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CreditCard, FileSearch, LayoutDashboard, type LucideIcon, ReceiptText, Scale, Settings, Users } from '@lucide/vue';

defineEmits<{ navigate: [] }>();

const page = usePage<SharedProps>();
// Issues needing action, shown on Transaksi (ADR-0010). The cached count never includes duplicate webhooks.
const openIssues = computed(() => page.props.openIssueCount ?? 0);
// One access level (ADR-0009): every signed-in user sees every item.
const items: { label: string; href: string; icon: LucideIcon }[] = [
    { label: 'Dashboard', href: routes.dashboard, icon: LayoutDashboard },
    { label: 'Transaksi', href: routes.transactions, icon: ReceiptText },
    { label: 'Event Pembayaran', href: routes.paymentEvents, icon: CreditCard },
    { label: 'Rekonsiliasi', href: routes.reconciliation, icon: Scale },
    { label: 'Audit Log', href: routes.auditLog, icon: FileSearch },
    { label: 'Pengguna', href: routes.users, icon: Users },
    { label: 'Pengaturan', href: routes.settings, icon: Settings },
];

const isActive = (href: string): boolean => page.url === href || page.url.startsWith(`${href}/`) || page.url.startsWith(`${href}?`);
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
                :class="isActive(item.href) ? 'bg-sidebar-active font-semibold text-white' : ''"
                :aria-current="isActive(item.href) ? 'page' : undefined"
                @click="$emit('navigate')"
            >
                <component :is="item.icon" class="size-5 shrink-0" :class="isActive(item.href) ? 'text-primary' : ''" aria-hidden="true" />
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
