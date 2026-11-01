<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Eye, EyeOff } from '@lucide/vue';
import { ref } from 'vue';

defineOptions({ inheritAttrs: false });
defineProps<{ id: string }>();
const model = defineModel<string>({ default: '' });
const visible = ref(false);
</script>

<template>
    <div class="relative">
        <Input :id="id" v-model="model" v-bind="$attrs" :type="visible ? 'text' : 'password'" class="h-12 pr-12" />
        <button
            type="button"
            class="absolute top-1/2 right-2 flex size-9 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground hover:text-foreground"
            :aria-label="visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
            :aria-pressed="visible"
            @click="visible = !visible"
        >
            <component :is="visible ? EyeOff : Eye" class="size-5" aria-hidden="true" />
        </button>
    </div>
</template>
