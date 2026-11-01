<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import AuthLayout from '@/components/layout/AuthLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import { Button } from '@/components/ui/button';
import { copy } from '@/lib/copy';
import { routes } from '@/lib/routes';
import type { SharedProps } from '@/types/backoffice';
import { Link, usePage } from '@inertiajs/vue3';
import { FileQuestion, ServerCrash, ShieldAlert } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps<{ status: number }>();
const page = usePage<SharedProps>();

const content = computed(() => {
    if (props.status === 403) return { title: copy.forbiddenTitle, body: copy.forbiddenBody, icon: ShieldAlert };
    if (props.status === 404) return { title: copy.notFoundTitle, body: copy.notFoundBody, icon: FileQuestion };

    return { title: copy.serverErrorTitle, body: copy.serverErrorBody, icon: ServerCrash };
});
</script>

<template>
    <AppLayout v-if="page.props.auth?.user" :title="content.title">
        <PanelCard>
            <EmptyState :title="content.title" :description="content.body" :icon="content.icon" data-testid="error-state">
                <Button as-child variant="outline"
                    ><Link :href="routes.dashboard">{{ copy.backToDashboard }}</Link></Button
                >
            </EmptyState>
        </PanelCard>
    </AppLayout>
    <AuthLayout v-else :title="content.title">
        <EmptyState :title="content.title" :description="content.body" :icon="content.icon">
            <Button as-child variant="outline"><Link :href="routes.login">Ke halaman masuk</Link></Button>
        </EmptyState>
    </AuthLayout>
</template>
