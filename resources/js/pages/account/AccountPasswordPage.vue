<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PasswordForm from '@/features/account/PasswordForm.vue';
import { copy } from '@/lib/copy';
import { routes } from '@/lib/routes';
import type { SharedProps } from '@/types/backoffice';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** Ganti Kata Sandi (ADR-0015): the only thing a person changes about their own account. */
const page = usePage<SharedProps>();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <AppLayout :title="copy.changePassword" description="Kata sandi untuk akun yang sedang Anda pakai.">
        <div class="grid max-w-4xl gap-x-12 gap-y-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,20rem)]">
            <section aria-labelledby="password-heading" class="max-w-md">
                <h2 id="password-heading" class="sr-only">{{ copy.changePassword }}</h2>
                <PasswordForm />
            </section>

            <aside v-if="user" class="self-start rounded-xl bg-tint-50 p-5" aria-label="Akun Anda">
                <p class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Masuk sebagai</p>
                <p class="mt-2 text-sm font-bold">{{ user.name }}</p>
                <p class="font-mono text-[13px] text-muted-foreground">@{{ user.username }}</p>
                <p class="mt-4 text-[13px] leading-relaxed text-muted-foreground">
                    Nama dan username tidak diubah dari sini. Nama diubah lewat menu
                    <Link :href="routes.users" class="font-bold text-primary-text underline decoration-tint-300 underline-offset-4 hover:decoration-tint-600"
                        >Pengguna</Link
                    >; username tetap sejak akun dibuat.
                </p>
            </aside>
        </div>
    </AppLayout>
</template>
