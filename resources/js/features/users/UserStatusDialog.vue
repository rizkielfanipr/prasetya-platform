<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { routes } from '@/lib/routes';
import type { UserRow } from '@/types/backoffice';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ user: UserRow }>();
const open = defineModel<boolean>('open', { default: false });
const submitting = ref(false);

function submit(): void {
    const url = props.user.isActive ? routes.userDeactivate(props.user.id) : routes.userActivate(props.user.id);

    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onStart: () => (submitting.value = true),
            onFinish: () => {
                submitting.value = false;
                open.value = false;
            },
        },
    );
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        :title="user.isActive ? `Nonaktifkan ${user.username}?` : `Aktifkan ${user.username}?`"
        :description="
            user.isActive
                ? 'Pengguna ini langsung keluar dari semua perangkat dan tidak dapat masuk sampai diaktifkan lagi. Riwayatnya tetap tersimpan.'
                : 'Pengguna ini dapat masuk lagi dengan kata sandinya dan membuka semua menu.'
        "
        :confirm-label="user.isActive ? 'Nonaktifkan' : 'Aktifkan'"
        :destructive="user.isActive"
        :submitting="submitting"
        @confirm="submit"
    >
    </ConfirmDialog>
</template>
