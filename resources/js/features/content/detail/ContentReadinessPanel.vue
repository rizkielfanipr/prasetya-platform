<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { contentActionLabels, contentField } from '@/lib/content';
import { copy } from '@/lib/copy';
import { routes } from '@/lib/routes';
import type { ContentActions, ContentItem, ContentRevision, RuleFinding } from '@/types/content';
import { router } from '@inertiajs/vue3';
import { CheckCircle2, Circle, XCircle } from '@lucide/vue';
import { type Component, computed, ref, watch } from 'vue';

/**
 * Kesiapan posting (ADR-0013): a presenter of what the server already decided, never a second state machine. The rows
 * read the saved revision and the server's brand findings; only "saved" comes from the screen. The one primary action
 * follows deterministically: unsaved changes → save; saved → Tandai siap posting, disabled with the server's reason.
 */
const props = defineProps<{
    item: ContentItem;
    revision: ContentRevision;
    findings: RuleFinding[];
    actions: ContentActions;
    dirty: boolean;
    saving: boolean;
    aiActive: boolean;
    errors: Partial<Record<string, string>>;
    /** Editing Ready content: saving makes a new revision and returns it to Draft. */
    editingReady: boolean;
}>();
const emit = defineEmits<{ save: []; cancelEdit: []; focusField: [name: string] }>();

const brandErrors = computed(() => props.findings.filter((finding) => finding.severity === 'ERROR').length);

interface Row {
    key: string;
    done: boolean;
    text: string;
    icon: Component;
    tone: string;
}
const rows = computed<Row[]>(() => {
    const row = (key: string, done: boolean, yes: string, no: string, blocking = false): Row => ({
        key,
        done,
        text: done ? yes : no,
        icon: done ? CheckCircle2 : blocking ? XCircle : Circle,
        tone: done ? 'text-success' : blocking ? 'text-danger' : 'text-muted-foreground',
    });

    return [
        row('caption', !!props.revision.caption, 'Caption sudah diisi', 'Caption belum diisi'),
        row('image', props.revision.asset !== null, 'Gambar 4:5 sudah diunggah', 'Gambar belum diunggah'),
        row('alt_text', !!props.revision.altText, 'Alt text sudah diisi', 'Alt text belum diisi'),
        row('brand', brandErrors.value === 0, 'Tidak ada pelanggaran aturan brand', `${brandErrors.value} pelanggaran aturan brand`, true),
        row('saved', !props.dirty, 'Semua perubahan tersimpan', 'Ada perubahan yang belum disimpan'),
    ];
});
const doneCount = computed(() => rows.value.filter((row) => row.done).length);

const errorList = computed(() =>
    Object.entries(props.errors)
        .filter((entry): entry is [string, string] => typeof entry[1] === 'string')
        .map(([name, message]) => ({ name, label: name === 'image' ? 'Gambar' : (contentField(name)?.label ?? name), message })),
);

type Mode = 'saving' | 'ai' | 'save' | 'idleEdit' | 'ready';
const mode = computed<Mode>(() => {
    if (props.saving) return 'saving';
    if (props.aiActive) return 'ai';
    if (props.dirty) return 'save';
    return props.editingReady ? 'idleEdit' : 'ready';
});
const readyReason = computed(() => props.actions.markReady);

// Announce progress, not keystrokes: only when a requirement flips or the screen becomes saved or unsaved.
const announcement = ref('');
watch(doneCount, (count) => (announcement.value = `${count} dari ${rows.value.length} syarat siap posting terpenuhi.`));
watch(
    () => props.dirty,
    (dirty) => (announcement.value = dirty ? 'Ada perubahan yang belum disimpan.' : 'Semua perubahan tersimpan.'),
);

const confirming = ref(false);
const submitting = ref(false);
function markReady(): void {
    router.post(
        routes.contentReady(props.item.id),
        { revision: props.revision.id },
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => ((submitting.value = false), (confirming.value = false)),
        },
    );
}
</script>

<template>
    <aside aria-labelledby="readiness-title" class="space-y-4 rounded-xl border bg-card p-4 sm:p-5" data-testid="readiness">
        <div class="flex items-baseline justify-between gap-3">
            <h2 id="readiness-title" class="text-base font-bold">Kesiapan posting</h2>
            <p class="text-xs font-semibold text-muted-foreground tabular" data-testid="readiness-count">{{ doneCount }} dari {{ rows.length }}</p>
        </div>
        <ul class="space-y-2" aria-label="Syarat siap posting">
            <li v-for="row in rows" :key="row.key" class="flex items-start gap-2 text-sm" :data-testid="`readiness-${row.key}`" :data-done="row.done">
                <component :is="row.icon" class="mt-0.5 size-4 shrink-0" :class="row.tone" aria-hidden="true" />
                <span :class="row.done ? '' : 'font-semibold'">{{ row.text }}</span>
            </li>
        </ul>

        <div v-if="errorList.length" role="alert" class="space-y-1 rounded-lg bg-danger-surface p-3 text-sm text-danger" data-testid="error-summary">
            <p class="font-semibold">Periksa isian berikut:</p>
            <ul class="space-y-1">
                <li v-for="error in errorList" :key="error.name">
                    <a :href="`#content-${error.name}`" class="underline" @click.prevent="emit('focusField', error.name)"
                        >{{ error.label }}: {{ error.message }}</a
                    >
                </li>
            </ul>
        </div>

        <div class="space-y-2 border-t pt-4">
            <Button v-if="mode === 'saving'" class="h-11 w-full" disabled aria-busy="true">{{ copy.saving }}</Button>
            <Button v-else-if="mode === 'ai'" class="h-11 w-full" disabled aria-busy="true">AI sedang menulis…</Button>
            <Button v-else-if="mode === 'save'" class="h-11 w-full" data-testid="save-content" @click="emit('save')">
                {{ editingReady ? 'Simpan dan kembalikan ke Draft' : 'Simpan perubahan' }}
            </Button>
            <template v-else-if="mode === 'ready'">
                <Button
                    class="h-11 w-full"
                    :disabled="readyReason !== null"
                    :aria-describedby="readyReason ? 'reason-markReady' : undefined"
                    data-testid="mark-ready"
                    @click="confirming = true"
                    >{{ contentActionLabels.markReady }}</Button
                >
                <p v-if="readyReason" id="reason-markReady" class="text-xs text-muted-foreground">{{ readyReason }}</p>
            </template>
            <p v-else class="text-xs text-muted-foreground">Ubah isian untuk membuat revisi baru.</p>
            <Button v-if="editingReady" variant="outline" class="h-11 w-full" :disabled="saving" @click="emit('cancelEdit')"> Kembali ke paket posting </Button>
        </div>
        <p class="sr-only" aria-live="polite">{{ announcement }}</p>

        <ConfirmDialog
            v-model:open="confirming"
            :title="`Tandai revisi ${revision.number} siap posting?`"
            description="Revisi ini dibekukan sebagai versi yang akan diposting. Perubahan berikutnya membuat revisi baru dan mengembalikan konten ke Draft."
            :confirm-label="contentActionLabels.markReady"
            :submitting="submitting"
            @confirm="markReady"
        />
    </aside>
</template>
