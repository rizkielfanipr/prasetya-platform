<script setup lang="ts">
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { ruleSeverities } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { RuleFinding } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2 } from '@lucide/vue';
import { computed } from 'vue';

/** The brand-rule check of the current revision. Errors block submission; warnings inform the writer and reviewer. */
const props = defineProps<{ findings: RuleFinding[]; version: number | null; submittedVersion: number | null }>();

const errors = computed(() => props.findings.filter((finding) => finding.severity === 'ERROR').length);
const ordered = computed(() => [...props.findings].sort((a, b) => (a.severity === b.severity ? 0 : a.severity === 'ERROR' ? -1 : 1)));
</script>

<template>
    <div class="space-y-3" data-testid="rule-findings">
        <p class="text-sm text-muted-foreground">
            <template v-if="version">Diperiksa dengan aturan brand versi {{ version }}.</template>
            <template v-else>Aturan brand belum diisi.</template>
            <template v-if="submittedVersion && submittedVersion !== version">
                Saat diajukan, revisi ini memenuhi aturan versi {{ submittedVersion }}.</template
            >
            <Link :href="routes.contentBrands" class="ml-1 font-medium text-primary-text hover:underline">Lihat Brand Knowledge</Link>
        </p>
        <p v-if="findings.length === 0" class="flex items-center gap-2 text-sm font-medium text-success">
            <CheckCircle2 class="size-4" aria-hidden="true" />Tidak ada pelanggaran atau peringatan.
        </p>
        <template v-else>
            <p class="text-sm font-semibold">
                {{ errors > 0 ? `${errors} pelanggaran harus diperbaiki sebelum diajukan.` : 'Hanya peringatan; konten tetap dapat diajukan.' }}
            </p>
            <ul class="space-y-2">
                <li v-for="(finding, index) in ordered" :key="index" class="flex flex-wrap items-start gap-2 text-sm">
                    <StatusBadge :status="ruleSeverities[finding.severity]" />
                    <span class="min-w-0 flex-1 break-words">{{ finding.message }}</span>
                </li>
            </ul>
        </template>
    </div>
</template>
