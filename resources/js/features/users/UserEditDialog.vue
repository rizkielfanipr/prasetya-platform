<script setup lang="ts">
import ConfirmDialog from '@/components/shared/ConfirmDialog.vue';
import FormField from '@/components/shared/FormField.vue';
import { Input } from '@/components/ui/input';
import { routes } from '@/lib/routes';
import type { UserRow } from '@/types/backoffice';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{ user: UserRow }>();
const open = defineModel<boolean>('open', { default: false });
const form = useForm({ name: props.user.name });

// Start from the current name each time the dialog opens.
watch(open, (value) => {
    if (value) {
        form.name = props.user.name;
        form.clearErrors();
    }
});

function submit(): void {
    form.put(routes.user(props.user.id), { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <ConfirmDialog
        v-model:open="open"
        :title="`Ubah ${user.username}`"
        description="Hanya nama yang dapat diubah."
        confirm-label="Simpan"
        :submitting="form.processing"
        @confirm="submit"
    >
        <div class="space-y-4">
            <FormField :id="`edit-name-${user.id}`" v-slot="{ describedBy, invalid }" label="Nama" :error="form.errors.name">
                <Input :id="`edit-name-${user.id}`" v-model="form.name" class="h-11" maxlength="255" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
            <FormField :id="`edit-username-${user.id}`" v-slot="{ describedBy }" label="Username" hint="Username tidak dapat diubah.">
                <Input :id="`edit-username-${user.id}`" :model-value="user.username" class="h-11" readonly :aria-describedby="describedBy" />
            </FormField>
        </div>
    </ConfirmDialog>
</template>
