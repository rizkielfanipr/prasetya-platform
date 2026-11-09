<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import DataTable, { type DataTableColumn } from '@/components/shared/DataTable.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import ContentNav from '@/features/content/ContentNav.vue';
import { formatUsd, generationStatuses } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { AiStatus, EvaluationRow, GenerationRun } from '@/types/content';
import { Button } from '@/components/ui/button';
import { Link, useForm } from '@inertiajs/vue3';
import { CheckCircle2, TriangleAlert } from '@lucide/vue';
import { computed, watch } from 'vue';

/** Pengaturan AI (ADR-0011): read-only status. Keys live in the server's environment and are never shown or entered here. */
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
    modelForm.put(routes.contentSettingsModel, { preserveScroll: true });
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
</script>

<template>
    <AppLayout title="Content Studio" description="Status AI dan riwayat draft AI. Konten tetap dapat ditulis manual kapan pun.">
        <ContentNav current="settings" />

        <PanelCard title="Penulis AI (teks)">
            <p
                v-if="status.unavailableReason"
                class="mb-4 flex items-start gap-2 rounded-lg bg-warning-surface p-3 text-sm text-warning"
                data-testid="ai-status"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{{ status.unavailableReason }}
            </p>
            <p v-else class="mb-4 flex items-start gap-2 rounded-lg bg-success-surface p-3 text-sm text-success" data-testid="ai-status">
                <CheckCircle2 class="mt-0.5 size-4 shrink-0" aria-hidden="true" />AI siap membuat draft. Hasilnya selalu draft yang Anda periksa sendiri.
            </p>
            <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Provider</dt>
                    <dd class="mt-1">{{ providerName }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Model</dt>
                    <dd class="mt-1 font-mono text-sm break-all">{{ status.model ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Batas per run</dt>
                    <dd class="mt-1">{{ status.maxOutputTokens }} token keluar · {{ status.maxAttempts }} percobaan · {{ status.timeoutSeconds }} detik</dd>
                </div>
            </dl>
            <form
                v-if="status.models.length > 1"
                class="mt-5 flex flex-wrap items-end gap-3 border-t pt-4"
                aria-label="Pilih model AI"
                @submit.prevent="saveModel"
            >
                <div class="flex min-w-60 flex-col gap-1.5">
                    <label for="ai-model" class="text-sm font-semibold">Model untuk draft berikutnya</label>
                    <select
                        id="ai-model"
                        v-model="modelForm.model"
                        class="h-11 rounded-md border border-input bg-card px-3 text-sm"
                        aria-describedby="ai-model-hint"
                    >
                        <option v-for="option in status.models" :key="option.name" :value="option.name">{{ option.name }}</option>
                    </select>
                    <p id="ai-model-hint" class="text-xs text-muted-foreground">
                        Daftar model diatur di server (<code>CONTENT_TEXT_MODELS</code>). Jalankan <code>content:eval-copy --model=…</code> sebelum mengganti.
                    </p>
                </div>
                <Button type="submit" :disabled="!modelForm.isDirty || modelForm.processing" :aria-busy="modelForm.processing">Simpan model</Button>
            </form>

            <p v-if="status.circuitOpenUntil" class="mt-4 text-sm font-medium text-danger">
                Provider gagal berulang kali; permintaan baru ditahan sampai {{ formatDateTime(status.circuitOpenUntil) }}.
            </p>
            <details class="mt-4 text-sm">
                <summary class="cursor-pointer font-semibold">Cara mengaktifkan</summary>
                <p class="mt-2 text-muted-foreground">
                    Isi di file <code>.env</code> server (bukan di sini): <code>CONTENT_TEXT_PROVIDER=openai_compatible</code>,
                    <code>CONTENT_TEXT_BASE_URL</code>, <code>CONTENT_TEXT_MODEL</code>, dan <code>CONTENT_TEXT_API_KEY</code>. Jalankan worker:
                    <code>php artisan queue:work --queue={{ status.queue }}</code
                    >. Sebelum dipakai, jalankan <code>php artisan content:eval-copy</code>.
                </p>
            </details>
        </PanelCard>

        <PanelCard title="Pencarian web (riset)">
            <p class="text-sm" data-testid="search-status">
                <template v-if="status.search.unavailableReason">{{ status.search.unavailableReason }}</template>
                <template v-else>Siap. Provider {{ status.search.provider }}.</template>
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                {{ status.search.callsThisWeek }} pencarian minggu ini · maksimal {{ status.search.maxResults }} hasil per pencarian. Key tidak pernah
                ditampilkan; agen memakai tool riset, bukan key.
            </p>
        </PanelCard>

        <PanelCard
            title="Evaluasi draft AI"
            description="Per model dan versi prompt: penilaian orang dan berapa yang akhirnya siap posting. Untuk membandingkan, bukan mengubah prompt otomatis."
        >
            <EmptyState v-if="evaluation.length === 0" title="Belum ada draft AI" />
            <ul v-else class="divide-y rounded-lg border" data-testid="ai-evaluation">
                <li
                    v-for="row in evaluation"
                    :key="`${row.model}-${row.promptVersion}`"
                    class="flex flex-wrap items-baseline justify-between gap-2 p-3 text-sm"
                >
                    <span
                        ><span class="font-mono">{{ row.model ?? '—' }}</span> · prompt {{ row.promptVersion }}</span
                    >
                    <span class="tabular"
                        >{{ row.drafts }} draft · {{ row.useful }} berguna · {{ row.notUseful }} kurang berguna · {{ row.approved }} siap posting<template
                            v-if="row.costUsd > 0"
                        >
                            · {{ formatUsd(row.costUsd) }}</template
                        ></span
                    >
                </li>
            </ul>
        </PanelCard>

        <PanelCard title="Riwayat draft AI" description="30 terakhir. Prompt dan jawaban mentah provider tidak disimpan." flush>
            <DataTable caption="Riwayat draft AI" :columns="columns" :rows="runs" :row-key="(row) => row.id">
                <template #cell-item="{ row }">
                    <Link :href="routes.contentItem(row.item.id)" class="line-clamp-2 min-w-40 hover:underline">{{ row.item.title }}</Link>
                    <span v-if="row.errorMessage" class="block text-xs font-normal text-muted-foreground">{{ row.errorMessage }}</span>
                </template>
                <template #cell-status="{ row }"><StatusBadge :status="generationStatuses[row.status]" /></template>
                <template #cell-model="{ row }"
                    ><span class="font-mono text-xs">{{ row.model ?? row.provider }}</span></template
                >
                <template #cell-usage="{ row }">{{ row.inputTokens + row.outputTokens }}</template>
                <template #cell-time="{ row }"
                    ><span class="whitespace-nowrap">{{ formatDateTime(row.createdAt) }}</span></template
                >
                <template #empty><EmptyState title="Belum ada draft AI" /></template>
            </DataTable>
        </PanelCard>
    </AppLayout>
</template>
