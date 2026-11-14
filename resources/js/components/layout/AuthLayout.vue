<script setup lang="ts">
import { brands } from '@/lib/brands';
import { copy } from '@/lib/copy';
import { Head } from '@inertiajs/vue3';

/**
 * Signed-out screens (login, guest errors). The world's signboard split in two: a flat tint field that names the
 * platform and the brands it serves, and a white work area that holds one task. No card, no shadow (DESIGN.md).
 */
defineProps<{ title: string; heading: string; description?: string }>();
</script>

<template>
    <Head :title="title" />
    <div class="grid min-h-screen bg-background lg:grid-cols-[minmax(360px,5fr)_7fr]">
        <aside class="flex flex-col gap-6 bg-tint-50 px-5 py-5 lg:justify-between lg:px-12 lg:py-12" aria-label="Prasetya Platform">
            <div class="flex items-center gap-3">
                <img src="/images/brands/platform-mark.svg" alt="" width="40" height="40" class="size-9 lg:size-10" />
                <span class="text-lg leading-none font-extrabold tracking-[-0.01em] text-foreground">
                    Prasetya <span class="font-semibold text-primary-text">Platform</span>
                </span>
            </div>

            <div class="hidden max-w-sm lg:block">
                <p class="text-2xl leading-tight font-extrabold tracking-[-0.01em] text-foreground">{{ copy.authHeading }}</p>
                <p class="mt-3 text-sm leading-relaxed text-muted-foreground">{{ copy.authBody }}</p>
            </div>

            <div class="hidden lg:block">
                <p class="mb-3 text-xs font-semibold text-sidebar-muted">{{ copy.managedFor }}</p>
                <ul class="flex flex-wrap items-center gap-x-6 gap-y-3">
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
        </aside>

        <main class="flex flex-col justify-center px-5 py-10 sm:px-10 lg:px-16">
            <div class="mx-auto w-full max-w-[400px]">
                <h1 class="text-2xl leading-tight font-extrabold tracking-[-0.01em]">{{ heading }}</h1>
                <p v-if="description" class="mt-1.5 text-sm text-muted-foreground">{{ description }}</p>
                <div class="mt-8"><slot /></div>
            </div>
        </main>

        <footer class="border-t border-border px-5 py-5 lg:hidden">
            <p class="mb-3 text-xs font-semibold text-sidebar-muted">{{ copy.managedFor }}</p>
            <ul class="flex flex-wrap items-center gap-x-6 gap-y-3">
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
        </footer>
    </div>
</template>
