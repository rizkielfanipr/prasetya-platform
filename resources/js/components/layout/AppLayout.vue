<script setup lang="ts">
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { Toaster } from '@/components/ui/sonner';
import { copy } from '@/lib/copy';
import type { SharedProps } from '@/types/backoffice';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppHeader from './AppHeader.vue';
import AppSidebar from './AppSidebar.vue';
import PageHeader from './PageHeader.vue';

defineProps<{ title: string; description?: string }>();

const page = usePage<SharedProps>();
const menuOpen = ref(false);

// The desktop sidebar's width is a per-browser convenience; storage may be unavailable, so it only ever helps.
const SIDEBAR_KEY = 'prasetya.sidebar-collapsed';
const collapsed = ref(false);
onMounted(() => {
    try {
        collapsed.value = window.localStorage.getItem(SIDEBAR_KEY) === '1';
    } catch {
        collapsed.value = false;
    }
});
function toggleSidebar(): void {
    collapsed.value = !collapsed.value;
    try {
        window.localStorage.setItem(SIDEBAR_KEY, collapsed.value ? '1' : '0');
    } catch {
        // Not remembered; the toggle still works for this page.
    }
}

watch(
    () => page.props.flash,
    (flash) => {
        if (flash.success) toast.success(flash.success);
        if (flash.error) toast.error(flash.error);
    },
    { immediate: true },
);

const stopNetworkListener = router.on('networkError', () => {
    toast.error(copy.networkError);
});
onBeforeUnmount(stopNetworkListener);
</script>

<template>
    <Head :title="title" />
    <a
        href="#main"
        class="sr-only z-50 rounded-md bg-card px-4 py-2 font-bold focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:shadow-overlay"
        >{{ copy.skipToContent }}</a
    >

    <aside
        id="app-sidebar"
        class="fixed inset-y-0 left-0 z-40 hidden border-r border-sidebar-border transition-[width] duration-200 ease-out motion-reduce:transition-none lg:block"
        :class="collapsed ? 'w-[76px]' : 'w-62'"
    >
        <AppSidebar :collapsed="collapsed" />
    </aside>

    <!-- Reka gives the drawer its focus trap, Escape to close, and focus return. -->
    <Sheet v-model:open="menuOpen">
        <SheetContent side="left" class="w-72 border-0 bg-sidebar p-0 text-sidebar-foreground">
            <SheetTitle class="sr-only">Navigasi</SheetTitle>
            <SheetDescription class="sr-only">Menu navigasi utama</SheetDescription>
            <AppSidebar @navigate="menuOpen = false" />
        </SheetContent>
    </Sheet>

    <div class="min-h-screen transition-[padding] duration-200 ease-out motion-reduce:transition-none" :class="collapsed ? 'lg:pl-[76px]' : 'lg:pl-62'">
        <AppHeader :sidebar-collapsed="collapsed" @open-menu="menuOpen = true" @toggle-sidebar="toggleSidebar" />
        <main id="main" class="mx-auto max-w-[1440px] space-y-5 px-4 pt-6 pb-10 md:px-6 lg:px-8 lg:pt-7">
            <PageHeader :title="title" :description="description" />
            <slot />
        </main>
    </div>
    <Toaster position="top-right" rich-colors close-button />
</template>
