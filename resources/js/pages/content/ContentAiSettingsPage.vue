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
import type { AiStatus, GenerationRun } from '@/types/content';
import { Button } from '@/components/ui/button';
import { Link, useForm } from '@inertiajs/vue3';
import { CheckCircle2, TriangleAlert } from '@lucide/vue';
import { computed, watch } from 'vue';

/** Pengaturan AI (ADR-0011): read-only status. Keys live in the server's environment and are never shown or entered here. */
const props = defineProps<{ status: AiStatus; runs: GenerationRun[] }>();

const modelForm = useForm({ model: props.status.model ?? '' });
watch(
    () => props.status.model,
    (model) => {
        modelForm.model = model ?? '';
        modelForm.defaults();
    },
);
const chosen = computed(() => props.status.models.find((m) => m.name === modelForm.model));

function saveModel(): void {
    modelForm.put(routes.contentSettingsModel, { preserveScroll: true });
}

const used = computed(() =>
    props.status.budget.weekLimit > 0 ? Math.min(100, Math.round((props.status.budget.spentThisWeek / props.status.budget.weekLimit) * 100)) : 0,
);
const providerName = computed(
    () =>
        ({ none: 'Belum dipilih', fake: 'Contoh (fake, tanpa biaya)', openai_compatible: 'OpenAI-compatible' })[props.status.provider] ?? props.status.provider,
);
const columns: DataTableColumn[] = [
    { key: 'item', label: 'Konten' },
    { key: 'status', label: 'Status' },
    { key: 'model', label: 'Model' },
    { key: 'usage', label: 'Token', align: 'right' },
    { key: 'cost', label: 'Biaya', align: 'right' },
    { key: 'time', label: 'Waktu' },
];
</script>

<template>
    <AppLayout title="Content Studio" description="Status AI, budget, dan riwayat draft AI. Konten tetap dapat ditulis manual kapan pun.">
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
                <CheckCircle2 class="mt-0.5 size-4 shrink-0" aria-hidden="true" />AI siap membuat draft. Hasilnya selalu draft yang perlu ditinjau.
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
                    <dt class="text-xs font-semibold text-muted-foreground">Harga per 1 juta token</dt>
                    <dd class="mt-1">masuk {{ formatUsd(status.priceInputPerMillion) }} · keluar {{ formatUsd(status.priceOutputPerMillion) }}</dd>
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
                        <template v-if="chosen"
                            >Harga: masuk {{ formatUsd(chosen.priceInputPerMillion) }} · keluar {{ formatUsd(chosen.priceOutputPerMillion) }} per 1 juta token. </template
                        >Daftar model diatur di server (<code>CONTENT_TEXT_MODELS</code>). Jalankan <code>content:eval-copy --model=…</code> sebelum mengganti.
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
                    <code>CONTENT_TEXT_BASE_URL</code>, <code>CONTENT_TEXT_MODEL</code>, <code>CONTENT_TEXT_API_KEY</code>, serta
                    <code>CONTENT_TEXT_PRICE_INPUT</code> dan <code>CONTENT_TEXT_PRICE_OUTPUT</code> (USD per 1 juta token). Jalankan worker:
                    <code>php artisan queue:work --queue={{ status.queue }}</code
                    >. Sebelum dipakai, jalankan <code>php artisan content:eval-copy</code>.
                </p>
            </details>
        </PanelCard>

        <PanelCard
            title="Budget minggu ini"
            :description="`Senin–Minggu WIB. Batas per run ${formatUsd(status.budget.runLimit)}, maksimal ${status.budget.runsPerRevision} kali AI per revisi.`"
        >
            <p class="text-sm" data-testid="ai-budget">
                Terpakai <span class="font-semibold">{{ formatUsd(status.budget.spentThisWeek) }}</span> dari {{ formatUsd(status.budget.weekLimit) }} ({{
                    used
                }}%)
            </p>
            <div class="mt-2 h-2 rounded-full bg-muted" aria-hidden="true">
                <div class="h-full rounded-full bg-primary-strong" :style="{ width: `${used}%` }" />
            </div>
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
                <template #cell-cost="{ row }">{{ formatUsd(row.costUsd) }}</template>
                <template #cell-time="{ row }"
                    ><span class="whitespace-nowrap">{{ formatDateTime(row.createdAt) }}</span></template
                >
                <template #empty><EmptyState title="Belum ada draft AI" /></template>
            </DataTable>
        </PanelCard>
    </AppLayout>
</template>
