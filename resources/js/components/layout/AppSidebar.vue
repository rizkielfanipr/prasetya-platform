<script setup lang="ts">
import { brands } from '@/lib/brands';
import { copy } from '@/lib/copy';
import { formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { SharedProps } from '@/types/backoffice';
import { BanknotesIcon, Cog6ToothIcon, MegaphoneIcon, Squares2X2Icon, UsersIcon } from '@heroicons/vue/24/outline';
import { Link, usePage } from '@inertiajs/vue3';
import { type Component, computed } from 'vue';

withDefaults(defineProps<{ collapsed?: boolean }>(), { collapsed: false });
defineEmits<{ navigate: [] }>();

const page = usePage<SharedProps>();
// Issues needing action, shown on Transaksi (ADR-0010). The cached count never includes duplicate webhooks.
const openIssues = computed(() => page.props.openIssueCount ?? 0);
// Approved posts due today or earlier and not yet marked posted (Content Studio, phase 7).
const contentDue = computed(() => page.props.contentDueCount ?? 0);

interface NavItem {
    label: string;
    href: string;
    icon: Component;
    section?: string;
    count?: () => number;
    countLabel?: (count: number) => string;
    countTestId?: string;
    countTone?: 'warning' | 'line';
}

// One access level (ADR-0009): every signed-in user sees every item. Each item is a station: a ringed icon, filled orange when active.
const items: NavItem[] = [
    { label: 'Dashboard', href: routes.dashboard, icon: Squares2X2Icon },
    {
        label: 'Transaksi',
        href: routes.transactions,
        icon: BanknotesIcon,
        count: () => openIssues.value,
        countLabel: (count) => `${count} isu perlu ditinjau`,
        countTestId: 'sidebar-issue-badge',
        countTone: 'warning',
    },
    {
        label: 'Content Studio',
        href: routes.contentCalendar,
        icon: MegaphoneIcon,
        section: routes.content,
        count: () => contentDue.value,
        countLabel: (count) => `${count} konten perlu diposting`,
        countTestId: 'sidebar-content-badge',
        countTone: 'line',
    },
    { label: 'Pengguna', href: routes.users, icon: UsersIcon },
    { label: 'Pengaturan', href: routes.settings, icon: Cog6ToothIcon },
];

const isActive = (href: string): boolean => page.url === href || page.url.startsWith(`${href}/`) || page.url.startsWith(`${href}?`);
const itemActive = (item: NavItem): boolean => isActive(item.section ?? item.href);
</script>

<template>
    <div class="flex h-full flex-col bg-sidebar text-sidebar-foreground">
        <Link
            :href="routes.dashboard"
            class="flex h-14 shrink-0 items-center gap-3 border-b border-sidebar-border px-5"
            :aria-label="collapsed ? 'Prasetya Platform, ke dashboard' : undefined"
            @click="$emit('navigate')"
        >
            <img src="/images/brands/platform-mark.svg" alt="" width="32" height="32" class="size-8 shrink-0" />
            <span v-if="!collapsed" class="text-base leading-none font-extrabold tracking-[-0.01em] whitespace-nowrap text-foreground">
                Prasetya <span class="font-semibold text-primary-text">Platform</span>
            </span>
        </Link>

        <nav aria-label="Navigasi utama" class="flex-1 overflow-y-auto py-5">
            <ul class="space-y-1">
                <li v-for="item in items" :key="item.href">
                    <Link
                        :href="item.href"
                        class="group/station relative flex h-11 items-center gap-3 rounded-r-lg pl-5 text-sm transition-colors duration-150 motion-reduce:transition-none"
                        :class="[
                            itemActive(item) ? 'bg-sidebar-active font-extrabold text-foreground' : 'font-semibold hover:bg-tint-50 hover:text-foreground',
                            collapsed ? 'mr-2' : 'mr-3 pr-3',
                        ]"
                        :aria-current="itemActive(item) ? 'page' : undefined"
                        :title="collapsed ? item.label : undefined"
                        @click="$emit('navigate')"
                    >
                        <span
                            class="relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full border-2 transition-colors duration-150 motion-reduce:transition-none"
                            :class="
                                itemActive(item)
                                    ? 'border-primary bg-primary text-white'
                                    : 'border-line-track bg-sidebar text-sidebar-muted group-hover/station:border-tint-400 group-hover/station:text-foreground'
                            "
                        >
                            <component :is="item.icon" class="size-4 stroke-2" aria-hidden="true" />
                        </span>
                        <span :class="collapsed ? 'sr-only' : 'min-w-0 flex-1 truncate'">{{ item.label }}</span>
                        <span
                            v-if="item.count && item.count() > 0"
                            class="rounded-md px-1.5 text-[11px] leading-5 font-extrabold tabular"
                            :class="[
                                item.countTone === 'warning' ? 'bg-warning-surface text-warning' : 'bg-tint-200 text-tint-800',
                                collapsed ? 'absolute top-0.5 left-10 z-20 ring-2 ring-sidebar' : '',
                            ]"
                            :aria-label="item.countLabel?.(item.count())"
                            :data-testid="item.countTestId"
                            >{{ formatNumber(item.count()) }}</span
                        >
                    </Link>
                </li>
            </ul>
        </nav>

        <div v-if="!collapsed" class="border-t border-sidebar-border px-5 py-4">
            <p class="mb-3 text-xs font-semibold text-sidebar-muted">{{ copy.managedFor }}</p>
            <ul class="flex flex-col items-start gap-3">
                <li v-for="brand in brands" :key="brand.label">
                    <img
                        :src="brand.logo.src"
                        :alt="brand.fullName"
                        :width="brand.logo.width"
                        :height="brand.logo.height"
                        class="w-auto"
                        :class="brand.logoClass"
                    />
                </li>
            </ul>
        </div>
    </div>
</template>
