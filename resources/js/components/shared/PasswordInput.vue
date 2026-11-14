<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });
defineProps<{ id: string }>();
const model = defineModel<string>({ default: '' });
const visible = ref(false);
</script>

<template>
    <div class="relative">
        <Input :id="id" v-model="model" v-bind="$attrs" :type="visible ? 'text' : 'password'" class="h-11 pr-12" />
        <button
            type="button"
            class="absolute top-1/2 right-1 flex size-9 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-tint-100 hover:text-foreground motion-reduce:transition-none"
            :aria-label="visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
            :aria-pressed="visible"
            @click="visible = !visible"
        >
            <component :is="visible ? EyeSlashIcon : EyeIcon" class="size-5 stroke-2" aria-hidden="true" />
        </button>
    </div>
</template>
