<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{ title: string }>();
</script>

<template>
    <Head :title="title" />
    <div class="flex min-h-screen flex-col items-center justify-center gap-6 p-4">
        <main class="w-[min(480px,100%)] rounded-xl border bg-card p-6 shadow-login sm:p-10">
            <div class="mb-6 flex flex-col items-center gap-2">
                <img src="/images/brands/platform-mark.svg" alt="" width="56" height="56" class="size-14" />
                <h1 class="text-xl font-bold">Prasetya Platform</h1>
            </div>
            <slot />
        </main>
        <p class="text-center text-sm text-muted-foreground">Prasetya Webworks • Satamu Invitation</p>
    </div>
</template>
