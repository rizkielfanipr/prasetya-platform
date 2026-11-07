<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { UserRow } from '@/types/backoffice';
import { Ban, KeyRound, MoreHorizontal, Pencil, UserCheck } from '@lucide/vue';
import { ref } from 'vue';
import UserEditDialog from './UserEditDialog.vue';
import UserPasswordDialog from './UserPasswordDialog.vue';
import UserStatusDialog from './UserStatusDialog.vue';

const props = defineProps<{ user: UserRow }>();

const editOpen = ref(false);
const passwordOpen = ref(false);
const statusOpen = ref(false);
const reasonId = `deactivate-reason-${props.user.id}`;
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="size-9" :aria-label="`Aksi untuk ${user.username}`">
                <MoreHorizontal class="size-4" aria-hidden="true" />
            </Button>
        </DropdownMenuTrigger>
        <!-- Each item opens a dialog; Reka returns focus to this trigger when the dialog closes. -->
        <DropdownMenuContent align="end" class="w-60">
            <DropdownMenuItem @select="editOpen = true"><Pencil aria-hidden="true" />Ubah nama</DropdownMenuItem>
            <DropdownMenuItem v-if="!user.isSelf" @select="passwordOpen = true"><KeyRound aria-hidden="true" />Atur kata sandi</DropdownMenuItem>
            <DropdownMenuSeparator />
            <DropdownMenuItem v-if="!user.isActive" @select="statusOpen = true"><UserCheck aria-hidden="true" />Aktifkan</DropdownMenuItem>
            <template v-else>
                <!-- The server enforces the same rules; a disabled item and its reason are only a hint. -->
                <DropdownMenuItem
                    :disabled="user.deactivateBlockedReason !== null"
                    :aria-describedby="user.deactivateBlockedReason ? reasonId : undefined"
                    class="text-danger focus:text-danger"
                    @select="statusOpen = true"
                >
                    <Ban aria-hidden="true" />Nonaktifkan
                </DropdownMenuItem>
                <p v-if="user.deactivateBlockedReason" :id="reasonId" class="px-2 pb-1.5 text-xs text-muted-foreground">{{ user.deactivateBlockedReason }}</p>
            </template>
        </DropdownMenuContent>
    </DropdownMenu>

    <UserEditDialog v-model:open="editOpen" :user="user" />
    <UserPasswordDialog v-if="!user.isSelf" v-model:open="passwordOpen" :user="user" />
    <UserStatusDialog v-model:open="statusOpen" :user="user" />
</template>
