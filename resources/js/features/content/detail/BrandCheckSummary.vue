<script setup lang="ts">
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { ruleSeverities } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { RuleFinding } from '@/types/content';
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, ChevronDown, Info, TriangleAlert, XCircle } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * Pemeriksaan brand of the saved revision (ADR-0013), disclosed by how much it matters: no rules is neutral, a clean
 * check is one line, warnings are counted and open on request, and errors are always open because they block Ready.
 */
const props = withDefaults(defineProps<{ findings: RuleFinding[]; version: number | null; stale?: boolean }>(), { stale: false });

// "No rules yet" is the absence of a check, not a finding to fix.
const listed = computed(() =>
    props.findings.filter((finding) => finding.code !== 'no_profile').sort((a, b) => (a.severity === b.severity ? 0 : a.severity === 'ERROR' ? -1 : 1)),
);
const errors = computed(() => listed.value.filter((finding) => finding.severity === 'ERROR').length);
const warnings = computed(() => listed.value.length - errors.value);
const open = ref(false);
const expanded = computed(() => errors.value > 0 || open.value);
</script>

<template>
    <section aria-labelledby="brand-check-title" class="space-y-2" data-testid="rule-findings">
        <!-- A clean or empty check is one line; the heading shows once there is something to read. -->
        <h2 id="brand-check-title" :class="listed.length ? 'text-base font-bold' : 'sr-only'">Pemeriksaan brand</h2>
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <p v-if="errors > 0" class="flex items-start gap-2 font-semibold text-danger" data-testid="brand-errors">
                <XCircle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{{ errors }} pelanggaran harus diperbaiki sebelum siap posting.
            </p>
            <p v-else-if="warnings > 0" class="flex items-start gap-2 font-semibold text-warning" data-testid="brand-warnings">
                <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{{ warnings }} saran dari aturan brand. Tidak menghalangi siap posting.
            </p>
            <p v-else-if="version === null" class="flex items-start gap-2 text-muted-foreground" data-testid="brand-none">
                <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />Aturan brand belum diisi, jadi konten belum diperiksa.
            </p>
            <p v-else class="flex items-start gap-2 font-semibold text-success" data-testid="brand-clean">
                <CheckCircle2 class="mt-0.5 size-4 shrink-0" aria-hidden="true" />Sesuai aturan brand versi {{ version }}.
            </p>
            <Button
                v-if="errors === 0 && warnings > 0"
                type="button"
                variant="ghost"
                class="h-11 gap-1 px-2"
                aria-controls="brand-findings-list"
                :aria-expanded="open"
                @click="open = !open"
            >
                {{ open ? 'Sembunyikan saran' : 'Lihat saran' }}
                <ChevronDown class="size-4 transition-transform motion-reduce:transition-none" :class="{ 'rotate-180': open }" aria-hidden="true" />
            </Button>
            <Link
                v-if="errors > 0 || version === null"
                :href="routes.contentBrands"
                class="inline-flex min-h-11 items-center font-medium text-primary-text hover:underline"
                >Buka Brand Knowledge</Link
            >
        </div>
        <p v-if="stale" class="text-xs text-muted-foreground">Diperiksa dari versi yang terakhir disimpan.</p>
        <ul v-show="expanded && listed.length > 0" id="brand-findings-list" class="space-y-2">
            <li v-for="(finding, index) in listed" :key="index" class="flex flex-wrap items-start gap-2 text-sm">
                <StatusBadge :status="ruleSeverities[finding.severity]" />
                <span class="min-w-0 flex-1 break-words">{{ finding.message }}</span>
            </li>
        </ul>
    </section>
</template>
