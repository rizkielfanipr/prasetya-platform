<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { contentActionLabels } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { ContentAction, ContentActions, ContentItem, ContentRevision, ReviewRequest } from '@/types/content';
import { areaLabels } from '@/lib/content';
import { formatDateTime } from '@/lib/format';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ContentDecisionDialog from './ContentDecisionDialog.vue';

/** Submit and review. Every button the user cannot use stays visible, disabled, with the server's reason beside it. */
const props = withDefaults(defineProps<{ item: ContentItem; revision: ContentRevision; actions: ContentActions; reviewRequest?: ReviewRequest | null }>(), {
    reviewRequest: null,
});

const submitOpen = ref(false);
const submitting = ref(false);
const dialog = ref<'approve' | 'requestRevision' | 'reject' | null>(null);

type ReviewAction = Extract<ContentAction, 'submit' | 'approve' | 'requestRevision' | 'reject'>;
const shown = computed<ReviewAction[]>(() => {
    switch (props.item.status) {
        case 'DRAFT':
            return ['submit', 'reject'];
        case 'REVISION_REQUESTED':
            return ['submit'];
        case 'READY_FOR_REVIEW':
            return ['approve', 'requestRevision', 'reject'];
        default:
            return [];
    }
});

const summary = computed(() => {
    const r = props.revision;
    switch (props.item.status) {
        case 'DRAFT':
            return 'Lengkapi konten, lalu ajukan untuk ditinjau. Pengguna lain yang menyetujui.';
        case 'READY_FOR_REVIEW':
            return `Revisi ${r.number} diajukan oleh ${r.submittedBy ?? '—'} dan menunggu tinjauan pengguna lain.`;
        case 'REVISION_REQUESTED':
            return 'Peninjau meminta revisi. Ubah konten sesuai catatan, simpan, lalu ajukan lagi.';
        case 'APPROVED':
            return `Revisi ${r.number} disetujui. Posting manual di Instagram, lalu tandai sudah dipublikasikan.`;
        case 'PUBLISHED':
            return 'Konten sudah dipublikasikan. Riwayatnya tetap tersimpan.';
        case 'REJECTED':
            return 'Konten ditolak dan tidak diproses lagi. Riwayatnya tetap tersimpan.';
    }
});

const dialogs = computed(() => ({
    approve: {
        url: routes.contentApprove(props.item.id),
        title: `Setujui revisi ${props.revision.number}?`,
        description: 'Revisi ini menjadi versi yang boleh diposting. Perubahan berikutnya membutuhkan persetujuan lagi.',
        confirmLabel: 'Setujui',
        noteLabel: 'Catatan untuk penulis',
        noteRequired: false,
        destructive: false,
    },
    requestRevision: {
        url: routes.contentRequestRevision(props.item.id),
        title: 'Minta revisi?',
        description: `Revisi ${props.revision.number} dikembalikan ke penulis. Revisi ini tetap tersimpan apa adanya.`,
        confirmLabel: 'Minta revisi',
        noteLabel: 'Apa yang perlu diubah',
        noteRequired: true,
        destructive: false,
        withAreas: true,
    },
    reject: {
        url: routes.contentReject(props.item.id),
        title: 'Tolak konten ini?',
        description: 'Konten ditolak tidak dapat diubah atau diposting lagi. Buat konten baru bila diperlukan.',
        confirmLabel: 'Tolak',
        noteLabel: 'Alasan penolakan',
        noteRequired: true,
        destructive: true,
    },
}));

function open(action: ReviewAction): void {
    if (action === 'submit') submitOpen.value = true;
    else dialog.value = action;
}

function submit(): void {
    router.post(
        routes.contentSubmit(props.item.id),
        { revision: props.revision.id },
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => ((submitting.value = false), (submitOpen.value = false)),
        },
    );
}
</script>

<template>
    <div class="space-y-4">
        <p class="text-sm" data-testid="review-summary">{{ summary }}</p>
        <div v-if="reviewRequest" class="space-y-1 rounded-lg bg-warning-surface p-3 text-sm" data-testid="review-request">
            <p class="font-semibold text-warning">
                Catatan {{ reviewRequest.actor }} · {{ formatDateTime(reviewRequest.at)
                }}<template v-if="reviewRequest.areas.length"> · {{ areaLabels(reviewRequest.areas) }}</template>
            </p>
            <p class="break-words whitespace-pre-line">{{ reviewRequest.note }}</p>
        </div>
        <ul v-if="shown.length" class="flex flex-wrap gap-x-3 gap-y-3">
            <li v-for="action in shown" :key="action" class="flex max-w-xs flex-col gap-1">
                <Button
                    :variant="action === 'reject' ? 'outline' : action === 'requestRevision' ? 'secondary' : 'default'"
                    :disabled="actions[action] !== null"
                    :aria-describedby="actions[action] ? `reason-${action}` : undefined"
                    @click="open(action)"
                    >{{ contentActionLabels[action] }}</Button
                >
                <p v-if="actions[action]" :id="`reason-${action}`" class="text-xs text-muted-foreground">{{ actions[action] }}</p>
            </li>
        </ul>

        <ConfirmDialog
            v-model:open="submitOpen"
            :title="`Ajukan revisi ${revision.number} untuk ditinjau?`"
            description="Setelah diajukan, revisi ini tidak dapat diubah. Pengguna lain perlu menyetujuinya sebelum diposting."
            confirm-label="Ajukan"
            :submitting="submitting"
            @confirm="submit"
        />
        <ContentDecisionDialog
            v-if="dialog"
            :key="dialog"
            :open="true"
            v-bind="dialogs[dialog]"
            :revision-id="revision.id"
            @update:open="(value) => !value && (dialog = null)"
        />
    </div>
</template>
