<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { contentActionLabels } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { ContentAction, ContentActions, ContentItem, ContentRevision } from '@/types/content';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ContentDecisionDialog from './ContentDecisionDialog.vue';

/**
 * The creator's own lifecycle (ADR-0012): mark Ready, return to Draft, or archive. There is no approval step. Every
 * button the user cannot use stays visible, disabled, with the server's reason beside it.
 */
type StatusAction = Extract<ContentAction, 'markReady' | 'returnToDraft' | 'archive'>;
const props = withDefaults(defineProps<{ item: ContentItem; revision: ContentRevision; actions: ContentActions; dirty?: boolean }>(), { dirty: false });

const UNSAVED = 'Simpan perubahan dulu sebelum menandai siap posting.';
/** The server's reason, or the unsaved-changes reason for Ready: Ready freezes the saved revision, not the screen. */
const reason = (action: StatusAction): string | null => (action === 'markReady' && props.dirty ? UNSAVED : null) ?? props.actions[action];

const confirming = ref<Exclude<StatusAction, 'archive'> | null>(null);
const archiving = ref(false);
const submitting = ref(false);

const shown = computed<StatusAction[]>(() => {
    switch (props.item.status) {
        case 'DRAFT':
            return ['markReady', 'archive'];
        case 'READY':
            return ['returnToDraft', 'archive'];
        default:
            return [];
    }
});

const summary = computed(() => {
    switch (props.item.status) {
        case 'DRAFT':
            return 'Lengkapi caption, alt text, dan gambar, lalu tandai siap posting.';
        case 'READY':
            return `Revisi ${props.revision.number} siap diposting. Posting manual di Instagram, lalu tandai sudah diposting. Mengubah konten membuat revisi baru dan mengembalikannya ke Draft.`;
        case 'PUBLISHED':
            return 'Konten sudah diposting. Riwayatnya tetap tersimpan.';
        case 'ARCHIVED':
            return 'Konten diarsipkan dan tidak diproses lagi. Riwayatnya tetap tersimpan.';
    }
});

const confirmations = computed(() => ({
    markReady: {
        url: routes.contentReady(props.item.id),
        title: `Tandai revisi ${props.revision.number} siap posting?`,
        description: 'Revisi ini dibekukan sebagai versi yang akan diposting. Perubahan berikutnya membuat revisi baru dan mengembalikan konten ke Draft.',
    },
    returnToDraft: {
        url: routes.contentReturnToDraft(props.item.id),
        title: 'Kembalikan ke Draft?',
        description: 'Konten tidak lagi muncul di Siap posting sampai ditandai siap lagi. Revisi yang ada tetap tersimpan.',
    },
}));

function open(action: StatusAction): void {
    if (action === 'archive') archiving.value = true;
    else confirming.value = action;
}

function confirm(): void {
    if (!confirming.value) return;
    router.post(
        confirmations.value[confirming.value].url,
        { revision: props.revision.id },
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => ((submitting.value = false), (confirming.value = null)),
        },
    );
}
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm" data-testid="status-summary">{{ summary }}</p>
        <ul v-if="shown.length" class="flex flex-wrap gap-x-3 gap-y-3">
            <li v-for="action in shown" :key="action" class="flex max-w-xs flex-col gap-1">
                <Button
                    :variant="action === 'archive' ? 'outline' : action === 'returnToDraft' ? 'secondary' : 'default'"
                    :disabled="reason(action) !== null"
                    :aria-describedby="reason(action) ? `reason-${action}` : undefined"
                    @click="open(action)"
                    >{{ contentActionLabels[action] }}</Button
                >
                <p v-if="reason(action)" :id="`reason-${action}`" class="text-xs text-muted-foreground">{{ reason(action) }}</p>
            </li>
        </ul>

        <ConfirmDialog
            :open="confirming !== null"
            :title="confirming ? confirmations[confirming].title : ''"
            :description="confirming ? confirmations[confirming].description : ''"
            :confirm-label="confirming ? contentActionLabels[confirming] : ''"
            :submitting="submitting"
            @update:open="(value: boolean) => !value && (confirming = null)"
            @confirm="confirm"
        />
        <ContentDecisionDialog
            v-if="archiving"
            :open="true"
            :url="routes.contentArchive(item.id)"
            :revision-id="revision.id"
            :title="`Arsipkan ${item.title}?`"
            description="Konten yang diarsipkan tidak dapat diubah atau diposting lagi. Revisi dan riwayatnya tetap tersimpan."
            confirm-label="Arsipkan"
            note-label="Alasan (opsional)"
            :note-required="false"
            note-field="reason"
            :extra="{ revision: revision.id }"
            destructive
            @update:open="(value) => !value && (archiving = false)"
        />
    </div>
</template>
