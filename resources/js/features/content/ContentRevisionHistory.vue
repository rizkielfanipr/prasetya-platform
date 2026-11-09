<script setup lang="ts">
import { areaLabels, contentDecisions, contentFields, revisionChanges } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import type { ContentRevision } from '@/types/content';
import { computed, ref, watch } from 'vue';

/** Every revision, newest first, with its decisions, and a field-by-field comparison of any two. */
const props = defineProps<{ revisions: ContentRevision[]; readyRevisionId: string | null; approvedRevisionId: string | null }>();

const before = ref('');
const after = ref('');
// Default to the newest revision against the one before it, again whenever a new revision arrives.
watch(
    () => props.revisions[0]?.id,
    () => {
        after.value = props.revisions[0]?.id ?? '';
        before.value = props.revisions[1]?.id ?? after.value;
    },
    { immediate: true },
);
const find = (id: string) => props.revisions.find((revision) => revision.id === id);
const comparison = computed(() => {
    const a = find(before.value);
    const b = find(after.value);
    if (!a || !b || a.id === b.id) return null;
    const changes = revisionChanges(a, b);

    return { a, b, image: changes.image, fields: contentFields.filter((field) => changes.fields.includes(field.key)) };
});
</script>

<template>
    <div class="space-y-6">
        <ol class="space-y-4" aria-label="Riwayat revisi">
            <li v-for="revision in revisions" :key="revision.id" class="relative border-l-2 border-border pl-4" :data-testid="`revision-${revision.number}`">
                <span class="absolute top-1.5 -left-[5px] size-2 rounded-full bg-muted-foreground" aria-hidden="true" />
                <p class="font-semibold">
                    Revisi {{ revision.number }}
                    <span v-if="revision.id === readyRevisionId" class="ml-1 text-xs font-semibold text-info">· siap posting</span>
                    <span v-else-if="revision.id === approvedRevisionId" class="ml-1 text-xs font-semibold text-success">· disetujui (alur lama)</span>
                </p>
                <p class="text-xs text-muted-foreground">
                    Ditulis {{ revision.author
                    }}<template v-if="revision.generatedBy"> dengan AI ({{ revision.generatedBy.model ?? revision.generatedBy.provider }})</template> ·
                    {{ formatDateTime(revision.createdAt) }}
                </p>
                <p class="text-xs text-muted-foreground">
                    <template v-if="revision.submittedAt">Dikunci oleh {{ revision.submittedBy }} · {{ formatDateTime(revision.submittedAt) }}</template>
                    <template v-else>Masih draft (dapat diubah)</template>
                </p>
                <ul v-if="revision.decisions.length" class="mt-2 space-y-1">
                    <li v-for="(decision, index) in revision.decisions" :key="index" class="text-xs">
                        <span class="font-semibold">{{ contentDecisions[decision.decision].label }}</span
                        ><template v-if="decision.areas.length"> ({{ areaLabels(decision.areas) }})</template> oleh {{ decision.actor }} ·
                        {{ formatDateTime(decision.at) }}
                        <span v-if="decision.note" class="block break-words text-muted-foreground">“{{ decision.note }}”</span>
                    </li>
                </ul>
            </li>
        </ol>

        <section v-if="revisions.length > 1" aria-labelledby="compare-heading" class="space-y-3 border-t pt-4">
            <h3 id="compare-heading" class="text-sm font-semibold">Bandingkan revisi</h3>
            <div class="flex flex-wrap items-end gap-3">
                <label class="flex flex-col gap-1 text-xs font-semibold text-muted-foreground"
                    >Dari
                    <select v-model="before" class="h-9 rounded-md border bg-card px-2 text-sm text-foreground">
                        <option v-for="revision in revisions" :key="revision.id" :value="revision.id">Revisi {{ revision.number }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-xs font-semibold text-muted-foreground"
                    >Ke
                    <select v-model="after" class="h-9 rounded-md border bg-card px-2 text-sm text-foreground">
                        <option v-for="revision in revisions" :key="revision.id" :value="revision.id">Revisi {{ revision.number }}</option>
                    </select>
                </label>
            </div>
            <p v-if="!comparison" class="text-sm text-muted-foreground">Pilih dua revisi yang berbeda.</p>
            <p v-else-if="comparison.fields.length === 0 && !comparison.image" class="text-sm text-muted-foreground">Tidak ada perbedaan.</p>
            <dl v-else class="space-y-3" data-testid="revision-diff">
                <div v-if="comparison.image" class="text-sm">
                    <dt class="font-semibold">Gambar</dt>
                    <dd class="text-muted-foreground">Gambar diganti.</dd>
                </div>
                <div v-for="field in comparison.fields" :key="field.key" class="space-y-1 text-sm">
                    <dt class="font-semibold">{{ field.label }}</dt>
                    <dd class="grid gap-2 md:grid-cols-2">
                        <div class="rounded-md bg-danger-surface p-2 break-words whitespace-pre-line">
                            <span class="block text-xs font-semibold text-danger">Revisi {{ comparison.a.number }}</span
                            >{{ comparison.a[field.key] ?? '—' }}
                        </div>
                        <div class="rounded-md bg-success-surface p-2 break-words whitespace-pre-line">
                            <span class="block text-xs font-semibold text-success">Revisi {{ comparison.b.number }}</span
                            >{{ comparison.b[field.key] ?? '—' }}
                        </div>
                    </dd>
                </div>
            </dl>
        </section>
    </div>
</template>
