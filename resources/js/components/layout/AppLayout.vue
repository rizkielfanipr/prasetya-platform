<script setup lang="ts">
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/components/ui/sheet';
import { Toaster } from '@/components/ui/sonner';
import { copy } from '@/lib/copy';
import type { SharedProps } from '@/types/backoffice';
import { Head, router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppHeader from './AppHeader.vue';
import AppSidebar from './AppSidebar.vue';

defineProps<{ title: string; description?: string }>();

const page = usePage<SharedProps>();
const menuOpen = ref(false);

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
    <a href="#main" class="sr-only z-50 rounded-md bg-card px-4 py-2 focus:not-sr-only focus:absolute focus:top-2 focus:left-2">Lewati ke konten utama</a>

    <aside class="fixed inset-y-0 left-0 hidden w-64 lg:block"><AppSidebar /></aside>

    <!-- Reka gives the drawer its focus trap, Escape to close, and focus return. -->
    <Sheet v-model:open="menuOpen">
        <SheetContent side="left" class="w-72 border-0 bg-sidebar p-0 text-sidebar-foreground">
            <SheetTitle class="sr-only">Navigasi</SheetTitle>
            <SheetDescription class="sr-only">Menu navigasi utama</SheetDescription>
            <AppSidebar @navigate="menuOpen = false" />
        </SheetContent>
    </Sheet>

    <div class="min-h-screen lg:pl-64">
        <AppHeader :title="title" :description="description" @open-menu="menuOpen = true" />
        <main id="main" class="space-y-4 p-4 md:space-y-5 md:p-5 lg:space-y-6 lg:p-6">
            <slot />
        </main>
    </div>
    <Toaster position="top-right" rich-colors close-button />
</template>
