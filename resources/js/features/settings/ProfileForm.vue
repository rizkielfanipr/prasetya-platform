<script setup lang="ts">
import PanelCard from '@/components/layout/PanelCard.vue';
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { copy } from '@/lib/copy';
import { routes } from '@/lib/routes';
import type { AuthUser } from '@/types/backoffice';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{ user: AuthUser }>();
const form = useForm({ name: props.user.name });
</script>

<template>
    <PanelCard title="Profil">
        <form class="space-y-4" novalidate @submit.prevent="form.put(routes.settingsProfile, { preserveScroll: true })">
            <FormField id="profile-name" v-slot="{ describedBy, invalid }" label="Nama" :error="form.errors.name">
                <Input id="profile-name" v-model="form.name" class="h-11" autocomplete="name" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
            <FormField id="profile-username" v-slot="{ describedBy }" label="Username" hint="Username tidak dapat diubah.">
                <Input id="profile-username" :model-value="user.username" class="h-11" readonly :aria-describedby="describedBy" />
            </FormField>
            <Button type="submit" :disabled="form.processing || !form.isDirty">{{ form.processing ? copy.saving : copy.save }}</Button>
        </form>
    </PanelCard>
</template>
