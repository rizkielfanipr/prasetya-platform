<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import ContentDecisionDialog from '@/features/content/ContentDecisionDialog.vue';
import { contentActionLabels } from '@/lib/content';
import { routes } from '@/lib/routes';
import type { ContentItem, ContentRevision } from '@/types/content';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * The lifecycle actions that live in Lainnya (ADR-0013): Kembali ke Draft for Ready content and Arsipkan for Draft or
 * Ready. Both send the revision the person saw, so the server refuses a stale request.
 */
const props = defineProps<{ item: ContentItem; revision: ContentRevision }>();
const returning = defineModel<boolean>('returning', { default: false });
const archiving = defineModel<boolean>('archiving', { default: false });

const submitting = ref(false);
function returnToDraft(): void {
    router.post(
        routes.contentReturnToDraft(props.item.id),
        { revision: props.revision.id },
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => ((submitting.value = false), (returning.value = false)),
        },
    );
}
</script>

<template>
    <ConfirmDialog
        v-model:open="returning"
        title="Kembalikan ke Draft?"
        :description="`Revisi ${revision.number} tetap tersimpan. Konten tidak lagi muncul di daftar Siap posting sampai ditandai siap lagi.`"
        :confirm-label="contentActionLabels.returnToDraft"
        :submitting="submitting"
        @confirm="returnToDraft"
    />
    <ContentDecisionDialog
        v-if="archiving"
        :open="true"
        :url="routes.contentArchive(item.id)"
        :revision-id="revision.id"
        :title="`Arsipkan ${item.title}?`"
        description="Konten yang diarsipkan tidak dapat diubah atau diposting lagi. Revisi dan riwayatnya tetap tersimpan."
        :confirm-label="contentActionLabels.archive"
        note-label="Alasan (opsional)"
        :note-required="false"
        note-field="reason"
        :extra="{ revision: revision.id }"
        destructive
        @update:open="(value) => !value && (archiving = false)"
    />
</template>
