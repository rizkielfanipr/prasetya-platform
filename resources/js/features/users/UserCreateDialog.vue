<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import { Button } from '@/components/ui/button';
import { routes } from '@/lib/routes';
import { useForm } from '@inertiajs/vue3';
import { UserPlus } from '@lucide/vue';
import { ref } from 'vue';
import UserFields from './UserFields.vue';

const open = ref(false);
const form = useForm({ name: '', username: '', password: '', password_confirmation: '' });

function submit(): void {
    form.post(routes.users, {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            form.reset();
        },
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        title="Tambah pengguna"
        description="Pengguna baru langsung aktif dan dapat membuka semua menu."
        confirm-label="Tambah pengguna"
        :submitting="form.processing"
        @confirm="submit"
    >
        <template #trigger>
            <Button class="gap-2"><UserPlus class="size-4" aria-hidden="true" />Tambah pengguna</Button>
        </template>
        <UserFields
            v-model:name="form.name"
            v-model:username="form.username"
            v-model:password="form.password"
            v-model:confirmation="form.password_confirmation"
            :errors="form.errors"
        />
    </ConfirmDialog>
</template>
