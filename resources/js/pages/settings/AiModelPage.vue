<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatUsd, generationStatuses } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { AiStatus, EvaluationRow, GenerationRun } from '@/types/content';
import { ArrowLeftIcon, CheckCircleIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

/**
 * Pengaturan → Model AI (ADR-0017, moved from Content Studio): the copy writer's status, the model for the next drafts,
 * and the record of drafts. Keys live in the server's environment and are never shown or entered here.
 */
const props = defineProps<{ status: AiStatus; runs: GenerationRun[]; evaluation: EvaluationRow[] }>();

const modelForm = useForm({ model: props.status.model ?? '' });
watch(
    () => props.status.model,
    (model) => {
        modelForm.model = model ?? '';
        modelForm.defaults();
    },
);
function saveModel(): void {
    modelForm.put(routes.settingsAiModel, { preserveScroll: true });
}

const providerName = computed(
    () =>
        ({ none: 'Belum dipilih', fake: 'Contoh (fake, tanpa biaya)', openai_compatible: 'OpenAI-compatible' })[props.status.provider] ?? props.status.provider,
);
const columns: DataTableColumn[] = [
    { key: 'item', label: 'Konten' },
    { key: 'status', label: 'Status' },
    { key: 'model', label: 'Model' },
    { key: 'usage', label: 'Token', align: 'right' },
    { key: 'time', label: 'Waktu' },
];
const label = 'text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase';
</script>

<template>
    <AppLayout title="Model AI" description="Penulis AI untuk draft konten. Konten tetap dapat ditulis manual kapan pun.">
        <Link :href="routes.settings" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary-text hover:underline">
            <ArrowLeftIcon class="size-4 stroke-2" aria-hidden="true" />Kembali ke pengaturan
        </Link>

        <section aria-labelledby="ai-writer" class="rounded-xl bg-tint-50 px-5 py-5 md:px-7">
            <h2 id="ai-writer" class="sr-only">Penulis AI</h2>
            <p v-if="status.unavailableReason" class="flex items-start gap-2 text-sm font-semibold text-warning" data-testid="ai-status">
                <ExclamationTriangleIcon class="mt-0.5 size-4 shrink-0 stroke-2" aria-hidden="true" />{{ status.unavailableReason }}
            </p>
            <p v-else class="flex items-start gap-2 text-sm font-semibold text-success" data-testid="ai-status">
                <CheckCircleIcon class="mt-0.5 size-4 shrink-0 stroke-2" aria-hidden="true" />AI siap membuat draft. Hasilnya selalu draft yang Anda periksa
                sendiri.
            </p>
            <dl class="mt-4 grid gap-x-6 gap-y-3 sm:grid-cols-3">
                <div>
                    <dt :class="label">Provider</dt>
                    <dd class="mt-0.5 text-sm font-semibold">{{ providerName }}</dd>
                </div>
                <div>
                    <dt :class="label">Model</dt>
                    <dd class="mt-0.5 font-mono text-sm font-semibold break-all">{{ status.model ?? '—' }}</dd>
                </div>
                <div>
                    <dt :class="label">Batas per draft</dt>
                    <dd class="mt-0.5 text-sm">
                        {{ status.maxOutputTokens }} token keluar<SeparatorDot />{{ status.maxAttempts }} percobaan<SeparatorDot />{{ status.timeoutSeconds }}
                        detik
                    </dd>
                </div>
            </dl>
            <p v-if="status.circuitOpenUntil" class="mt-4 text-sm font-semibold text-danger">
                Provider gagal berulang kali; permintaan baru ditahan sampai {{ formatDateTime(status.circuitOpenUntil) }}.
            </p>
        </section>

        <section v-if="status.models.length > 1" aria-labelledby="ai-model-heading" class="border-t border-border pt-4">
            <h2 id="ai-model-heading" class="text-sm font-extrabold">Model untuk draft berikutnya</h2>
            <form class="mt-3 flex flex-wrap items-end gap-3" aria-labelledby="ai-model-heading" @submit.prevent="saveModel">
                <div class="flex min-w-60 flex-col gap-1.5">
                    <label for="ai-model" :class="label">Model</label>
                    <select
                        id="ai-model"
                        v-model="modelForm.model"
                        class="h-11 rounded-md border border-input bg-card px-3 text-sm font-semibold"
                        aria-describedby="ai-model-hint"
                    >
                        <option v-for="option in status.models" :key="option.name" :value="option.name">{{ option.name }}</option>
                    </select>
                </div>
                <Button type="submit" class="h-11" :disabled="!modelForm.isDirty || modelForm.processing" :aria-busy="modelForm.processing"
                    >Simpan model</Button
                >
            </form>
            <p id="ai-model-hint" class="mt-2 text-xs text-muted-foreground">
                Daftar model diatur di server (<code>CONTENT_TEXT_MODELS</code>). Jalankan <code>content:eval-copy --model=…</code> sebelum mengganti.
            </p>
        </section>

        <section aria-labelledby="ai-evaluation-heading" class="border-t border-border pt-4">
            <h2 id="ai-evaluation-heading" class="text-sm font-extrabold">Hasil per model</h2>
            <p class="mt-0.5 text-[13px] text-muted-foreground">Berapa draft AI per model dan versi prompt yang akhirnya ditandai Ready.</p>
            <EmptyState v-if="evaluation.length === 0" class="mt-3" title="Belum ada draft AI" />
            <ul v-else class="mt-2 divide-y divide-border" data-testid="ai-evaluation">
                <li
                    v-for="row in evaluation"
                    :key="`${row.model}-${row.promptVersion}`"
                    class="flex flex-wrap items-baseline justify-between gap-2 py-2.5 text-sm"
                >
                    <span
                        ><span class="font-mono font-semibold">{{ row.model ?? '—' }}</span
                        ><SeparatorDot />prompt {{ row.promptVersion }}</span
                    >
                    <span class="tabular"
                        >{{ row.drafts }} draft<SeparatorDot />{{ row.approved }} Ready<template v-if="row.costUsd > 0"
                            ><SeparatorDot />{{ formatUsd(row.costUsd) }}</template
                        ></span
                    >
                </li>
            </ul>
        </section>

        <section aria-labelledby="ai-runs-heading" class="border-t border-border pt-4">
            <h2 id="ai-runs-heading" class="text-sm font-extrabold">Riwayat draft AI</h2>
            <p class="mt-0.5 mb-3 text-[13px] text-muted-foreground">30 terakhir. Prompt dan jawaban mentah provider tidak disimpan.</p>
            <DataTable caption="Riwayat draft AI" :columns="columns" :rows="runs" :row-key="(row) => row.id">
                <template #cell-item="{ row }">
                    <Link :href="routes.contentItem(row.item.id)" class="line-clamp-2 min-w-40 font-semibold hover:underline">{{ row.item.title }}</Link>
                    <span v-if="row.errorMessage" class="block text-xs font-normal text-muted-foreground">{{ row.errorMessage }}</span>
                </template>
                <template #cell-status="{ row }"><StatusBadge :status="generationStatuses[row.status]" /></template>
                <template #cell-model="{ row }"
                    ><span class="font-mono text-xs font-semibold">{{ row.model ?? row.provider }}</span></template
                >
                <template #cell-usage="{ row }"
                    ><span class="tabular">{{ row.inputTokens + row.outputTokens }}</span></template
                >
                <template #cell-time="{ row }"
                    ><span class="whitespace-nowrap tabular">{{ formatDateTime(row.createdAt) }}</span></template
                >
                <template #empty><EmptyState title="Belum ada draft AI" /></template>
            </DataTable>
        </section>
    </AppLayout>
</template>
