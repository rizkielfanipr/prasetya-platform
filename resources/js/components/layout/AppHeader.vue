<script setup lang="ts">
import SearchInput from '@/components/shared/SearchInput.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { usePermissions } from '@/composables/usePermissions';
import { copy } from '@/lib/copy';
import { routes } from '@/lib/routes';
import { roleLabels } from '@/lib/status';
import type { SharedProps } from '@/types/backoffice';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, ChevronDown, LogOut, Menu, Settings, UserRound } from '@lucide/vue';
import { computed, ref } from 'vue';
import PageHeader from './PageHeader.vue';

defineProps<{ title: string; description?: string }>();
defineEmits<{ openMenu: [] }>();

const page = usePage<SharedProps>();
const { can } = usePermissions();
const user = computed(() => page.props.auth.user);
const issues = computed(() => page.props.openIssueCount ?? 0);
const search = ref('');

function submitSearch(): void {
    router.get(routes.transactions, search.value ? { search: search.value } : {});
}
</script>

<template>
    <header class="flex min-h-[72px] items-center gap-3 border-b bg-background px-4 py-3 lg:gap-4 lg:px-6">
        <Button variant="ghost" size="icon" class="shrink-0 lg:hidden" :aria-label="copy.openMenu" @click="$emit('openMenu')">
            <Menu class="size-5" aria-hidden="true" />
        </Button>

        <PageHeader :title="title" :description="description" class="flex-1" />

        <form role="search" class="hidden w-72 shrink-0 md:block" @submit.prevent="submitSearch">
            <SearchInput id="global-search" v-model="search" :label="copy.searchPlaceholder" />
        </form>

        <Link
            v-if="can('reconciliation.view')"
            :href="routes.reconciliation"
            class="relative flex size-10 shrink-0 items-center justify-center rounded-md hover:bg-muted"
            :aria-label="copy.issuesLabel(issues)"
        >
            <Bell class="size-5" aria-hidden="true" />
            <span v-if="issues > 0" class="absolute top-2 right-2 size-2 rounded-full bg-primary-strong" aria-hidden="true" />
        </Link>

        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button variant="ghost" class="h-10 shrink-0 gap-2 px-2" data-testid="user-menu">
                    <span class="flex size-8 items-center justify-center rounded-full bg-muted"><UserRound class="size-4" aria-hidden="true" /></span>
                    <span class="hidden max-w-32 truncate font-semibold sm:inline">{{ user?.name }}</span>
                    <span class="sr-only sm:hidden">Menu akun {{ user?.name }}</span>
                    <ChevronDown class="size-4" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="w-56">
                <DropdownMenuLabel>
                    <span class="block truncate">{{ user?.name }}</span>
                    <span class="block text-xs font-normal text-muted-foreground">{{ user ? roleLabels[user.role] : '' }}</span>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuItem as-child>
                    <Link :href="routes.settings" class="w-full"><Settings aria-hidden="true" />{{ copy.profile }}</Link>
                </DropdownMenuItem>
                <DropdownMenuItem as-child>
                    <Link :href="routes.logout" method="post" as="button" class="w-full" data-testid="logout"
                        ><LogOut aria-hidden="true" />{{ copy.logout }}</Link
                    >
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </header>
</template>
