<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import type { SettingsAdmin } from '@/types/backoffice';
import type { StatusDefinition } from '@/lib/status';
import { routes } from '@/lib/routes';
import { ArrowUpRightIcon, CheckCircleIcon, ClockIcon, CpuChipIcon, ExclamationTriangleIcon, SignalIcon, XCircleIcon } from '@heroicons/vue/24/outline';
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';

/**
 * Pengaturan (ADR-0015, ADR-0016): platform configuration only — a person's own account lives in the account menu.
 * The hub answers two questions before it lists anything: is the platform wired up, and where do I go next.
 */
const props = defineProps<{ admin: SettingsAdmin }>();

const gatewayTone = computed<StatusDefinition>(() => {
    if (props.admin.gateway.driver === 'fake') {
        return { label: 'Gateway fake (bukan produksi)', tone: 'warning', icon: ExclamationTriangleIcon };
    }

    return props.admin.gateway.configured
        ? { label: 'Terhubung', tone: 'success', icon: CheckCircleIcon }
        : { label: 'Belum dikonfigurasi', tone: 'danger', icon: XCircleIcon };
});

const failedJobs = computed(() => props.admin.application.failedJobs);

interface Destination {
    label: string;
    description: string;
    href: string;
    icon: Component;
    testId: string;
}

const destinations: Destination[] = [
    {
        label: 'Aktivitas Sistem',
        description: 'Masuk, keluar, dan perubahan pengaturan di luar transaksi. Catatan tidak dapat diubah.',
        href: routes.settingsActivity,
        icon: ClockIcon,
        testId: 'open-activity',
    },
    {
        label: 'Log Gateway',
        description: 'Setiap webhook dari gateway, termasuk yang ditolak atau duplikat. Untuk menelusuri masalah.',
        href: routes.gatewayLog(),
        icon: SignalIcon,
        testId: 'open-gateway-log',
    },
    {
        label: 'Model AI',
        description: 'Model yang dipakai Content Studio dan kunci yang tersimpan.',
        href: routes.settingsAi,
        icon: CpuChipIcon,
        testId: 'open-ai-model',
    },
];
</script>

<template>
    <AppLayout title="Pengaturan" description="Konfigurasi platform dan catatan sistem. Kata sandi Anda diganti dari menu akun.">
        <section aria-labelledby="platform-heading" class="rounded-xl bg-tint-50 p-5 md:p-6">
            <h2 id="platform-heading" class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Koneksi gateway</h2>
            <dl class="mt-4 grid gap-x-6 gap-y-4 border-t border-tint-200 pt-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Gateway</dt>
                    <dd class="mt-1.5">
                        <span data-testid="gateway-status"><StatusBadge :status="gatewayTone" /></span>
                        <span class="mt-1 block text-[13px] text-muted-foreground">{{ admin.gateway.environment }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Lingkungan</dt>
                    <dd class="text-sign-ink mt-1.5 text-[14px]">{{ admin.application.environment }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Zona waktu</dt>
                    <dd class="text-sign-ink mt-1.5 text-[14px]">{{ admin.application.timezone }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Antrean gagal</dt>
                    <dd class="mt-1.5 text-[14px]" :class="failedJobs > 0 ? 'text-danger-text font-bold' : 'text-sign-ink'">
                        {{ failedJobs > 0 ? `${failedJobs} job perlu ditinjau` : 'Bersih' }}
                    </dd>
                </div>
            </dl>
        </section>

        <section aria-labelledby="destinations-heading" class="space-y-3">
            <div>
                <h2 id="destinations-heading" class="text-[14px] font-extrabold">Catatan dan model</h2>
                <p class="mt-1 text-[13px] text-muted-foreground">Riwayat dan konfigurasi yang jarang diubah, dipisah dari layar kerja harian.</p>
            </div>
            <ul class="divide-y divide-border border-y border-border">
                <li v-for="destination in destinations" :key="destination.testId">
                    <Link
                        :href="destination.href"
                        :data-testid="destination.testId"
                        class="group focus-visible:outline-line-orange flex items-start gap-4 py-4 focus-visible:outline-2 focus-visible:outline-offset-2"
                    >
                        <component :is="destination.icon" class="text-sign-ink mt-0.5 size-5 shrink-0 stroke-2" aria-hidden="true" />
                        <span class="min-w-0 flex-1">
                            <span class="text-ink block text-[14px] font-bold group-hover:text-primary-text">{{ destination.label }}</span>
                            <span class="mt-0.5 block text-[13px] leading-relaxed text-muted-foreground">{{ destination.description }}</span>
                        </span>
                        <ArrowUpRightIcon class="mt-1 size-4 shrink-0 stroke-2 text-muted-foreground group-hover:text-primary-text" aria-hidden="true" />
                    </Link>
                </li>
            </ul>
        </section>

        <section aria-labelledby="application-heading" class="border-t border-border pt-4">
            <h2 id="application-heading" class="text-[14px] font-extrabold">Aplikasi</h2>
            <p class="mt-1 text-[13px] text-muted-foreground">Diatur dari berkas lingkungan; hanya dibaca di sini.</p>
            <dl class="mt-4 grid gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Path notifikasi</dt>
                    <dd class="text-sign-ink mt-1 font-mono text-[13px] break-all">{{ admin.gateway.notificationPath }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Batas pending</dt>
                    <dd class="text-sign-ink mt-1 text-[14px]">{{ admin.application.pendingThresholdMinutes }} menit</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Origin API yang diizinkan</dt>
                    <dd class="text-sign-ink mt-1 font-mono text-[13px] break-all">
                        {{ admin.application.allowedOrigins.join(', ') || '—' }}
                    </dd>
                </div>
            </dl>
        </section>
    </AppLayout>
</template>
