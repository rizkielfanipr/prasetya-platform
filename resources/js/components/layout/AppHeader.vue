<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { copy } from '@/lib/copy';
import { routes } from '@/lib/routes';
import type { SharedProps } from '@/types/backoffice';
import {
    ArrowRightStartOnRectangleIcon,
    Bars3Icon,
    BellIcon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    ChevronDownIcon,
    Cog6ToothIcon,
} from '@heroicons/vue/24/outline';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Navigation and account only (ADR-0014): the page title lives in the content. */
defineProps<{ sidebarCollapsed: boolean }>();
defineEmits<{ openMenu: []; toggleSidebar: [] }>();

const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user);
const initial = computed(() => user.value?.name.trim().charAt(0).toUpperCase() ?? '');
const issues = computed(() => page.props.openIssueCount ?? 0);
</script>

<template>
    <header class="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-border bg-background px-3 md:px-6">
        <Button variant="ghost" size="icon" class="size-11 shrink-0 lg:hidden" :aria-label="copy.openMenu" @click="$emit('openMenu')">
            <Bars3Icon class="size-5 stroke-2" aria-hidden="true" />
        </Button>
        <Button
            variant="ghost"
            size="icon"
            class="hidden size-10 shrink-0 lg:inline-flex"
            :aria-label="sidebarCollapsed ? copy.expandSidebar : copy.collapseSidebar"
            :aria-expanded="!sidebarCollapsed"
            aria-controls="app-sidebar"
            @click="$emit('toggleSidebar')"
        >
            <ChevronDoubleRightIcon v-if="sidebarCollapsed" class="size-5 stroke-2" aria-hidden="true" />
            <ChevronDoubleLeftIcon v-else class="size-5 stroke-2" aria-hidden="true" />
        </Button>

        <div class="ml-auto flex items-center gap-1">
            <Link
                :href="routes.transactionsAttention"
                class="relative flex size-11 shrink-0 items-center justify-center rounded-md text-sidebar-foreground transition-colors hover:bg-tint-100 hover:text-foreground motion-reduce:transition-none"
                :aria-label="copy.issuesLabel(issues)"
            >
                <BellIcon class="size-5 stroke-2" aria-hidden="true" />
                <span v-if="issues > 0" class="absolute top-2.5 right-2.5 size-2.5 rounded-full bg-primary ring-2 ring-background" aria-hidden="true" />
            </Link>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <Button variant="ghost" class="h-11 shrink-0 gap-2 px-2 hover:bg-tint-100" data-testid="user-menu">
                        <span
                            class="flex size-8 items-center justify-center rounded-full bg-tint-200 text-sm font-extrabold text-tint-800"
                            aria-hidden="true"
                            >{{ initial }}</span
                        >
                        <span class="hidden max-w-32 truncate font-bold sm:inline">{{ user?.name }}</span>
                        <span class="sr-only sm:hidden">Menu akun {{ user?.name }}</span>
                        <ChevronDownIcon class="size-4 stroke-2" aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56">
                    <DropdownMenuLabel>
                        <span class="block truncate">{{ user?.name }}</span>
                        <span class="block text-xs font-normal text-muted-foreground">{{ user ? `@${user.username}` : '' }}</span>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link :href="routes.settings" class="w-full"><Cog6ToothIcon class="stroke-2" aria-hidden="true" />{{ copy.profile }}</Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem as-child>
                        <Link :href="routes.logout" method="post" as="button" class="w-full" data-testid="logout"
                            ><ArrowRightStartOnRectangleIcon class="stroke-2" aria-hidden="true" />{{ copy.logout }}</Link
                        >
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
