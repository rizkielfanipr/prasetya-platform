<script setup lang="ts">
import { formatFileSize, imageRequirements } from '@/lib/content';
import type { ContentAsset } from '@/types/content';
import { ref, watch } from 'vue';

/**
 * The post image: the stored one, or a preview of the file about to be uploaded. The preview is a data: URL because
 * the CSP allows no blob: images. The server decides acceptance from the bytes; the size check here only warns early.
 */
const props = defineProps<{ asset: ContentAsset | null; disabled: boolean; error?: string; alt: string | null }>();
const file = defineModel<File | null>({ default: null });
const preview = ref<string | null>(null);
const input = ref<HTMLInputElement | null>(null);
const warning = ref<string | null>(null);

watch(file, (next) => {
    preview.value = null;
    warning.value = null;
    if (!next) {
        if (input.value) input.value.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = () => {
        const url = typeof reader.result === 'string' ? reader.result : null;
        preview.value = url;
        if (!url) return;
        const image = new Image();
        image.onload = () => {
            const { naturalWidth: width, naturalHeight: height } = image;
            if (width * 5 !== height * 4 || width < 1080) {
                warning.value = `Gambar ini ${width}×${height}. Yang diterima: rasio tepat 4:5, minimal 1080×1350.`;
            }
        };
        image.src = url;
    };
    reader.readAsDataURL(next);
});

function choose(event: Event): void {
    const input = event.target as HTMLInputElement;
    file.value = input.files?.[0] ?? null;
}

const describedBy = ['content-image-hint', props.error ? 'content-image-error' : null, 'content-image-warning'].filter(Boolean).join(' ');
</script>

<template>
    <div class="space-y-3">
        <div class="mx-auto aspect-[4/5] w-full max-w-sm overflow-hidden rounded-lg border bg-muted">
            <img v-if="preview" :src="preview" alt="Pratinjau gambar yang akan diunggah" class="size-full object-cover" />
            <img v-else-if="asset" :src="asset.url" :alt="alt ?? 'Gambar posting'" :width="asset.width" :height="asset.height" class="size-full object-cover" />
            <p v-else class="flex size-full items-center justify-center p-6 text-center text-sm text-muted-foreground">Belum ada gambar</p>
        </div>
        <p v-if="asset && !preview" class="text-center text-xs text-muted-foreground">
            {{ asset.width }}×{{ asset.height }} · {{ formatFileSize(asset.sizeBytes) }} · {{ asset.mimeType.replace('image/', '').toUpperCase() }}
        </p>
        <div v-if="!disabled" class="space-y-1.5">
            <label for="content-image" class="text-sm font-semibold">{{ asset ? 'Ganti gambar' : 'Unggah gambar' }}</label>
            <input
                id="content-image"
                ref="input"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="block w-full text-sm file:mr-3 file:h-9 file:rounded-md file:border file:bg-secondary file:px-3 file:text-sm file:font-medium"
                :aria-describedby="describedBy"
                :aria-invalid="error ? true : undefined"
                @change="choose"
            />
            <p id="content-image-hint" class="text-xs text-muted-foreground">{{ imageRequirements }}</p>
            <p v-if="error" id="content-image-error" role="alert" class="text-xs font-medium text-danger">{{ error }}</p>
            <p id="content-image-warning" class="text-xs font-medium text-warning" aria-live="polite">{{ warning }}</p>
        </div>
    </div>
</template>
