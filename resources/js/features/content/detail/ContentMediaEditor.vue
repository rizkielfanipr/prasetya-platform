<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Textarea } from '@/components/ui/textarea';
import { contentField, formatFileSize, imageRequirements } from '@/lib/content';
import { formatNumber } from '@/lib/format';
import type { ContentAsset } from '@/types/content';
import { ImageUp } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

/**
 * Media dan alt text (ADR-0013): the image and its description are one job, so alt text sits beside the preview on
 * wider screens and right under it on phones. The preview of a chosen file is a data: URL because the CSP allows no
 * blob: images; the size check here only warns early, the server decides from the bytes.
 */
const props = defineProps<{
    asset: ContentAsset | null;
    readonly: boolean;
    errors: { image?: string; alt_text?: string };
    /** The saved revision's visual direction, shown as a reference while the image is made. */
    visualDirection: string | null;
}>();
const file = defineModel<File | null>('image', { default: null });
const altText = defineModel<string>('altText', { required: true });

const altField = contentField('alt_text');
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
    const target = event.target as HTMLInputElement;
    file.value = target.files?.[0] ?? null;
}

const imageDescribedBy = computed(() =>
    ['content-image-hint', props.errors.image ? 'content-image-error' : null, 'content-image-warning'].filter(Boolean).join(' '),
);
</script>

<template>
    <section aria-labelledby="media-title" class="space-y-4" data-testid="media-area">
        <h2 id="media-title" class="text-base font-bold">Media dan alt text</h2>
        <div class="grid gap-5 sm:grid-cols-[11rem_minmax(0,1fr)] xl:grid-cols-[12rem_minmax(0,1fr)]">
            <div class="mx-auto aspect-[4/5] w-full max-w-56 overflow-hidden rounded-lg border bg-muted sm:max-w-none">
                <img v-if="preview" :src="preview" alt="Pratinjau gambar yang akan diunggah" width="1080" height="1350" class="size-full object-cover" />
                <img
                    v-else-if="asset"
                    :src="asset.url"
                    :alt="altText || 'Gambar posting'"
                    :width="asset.width"
                    :height="asset.height"
                    class="size-full object-cover"
                    data-testid="media-image"
                />
                <p v-else class="flex size-full items-center justify-center p-4 text-center text-sm text-muted-foreground">Belum ada gambar</p>
            </div>

            <div class="min-w-0 space-y-4">
                <FormField id="content-alt_text" label="Alt text *" :error="errors.alt_text" :hint="altField?.hint">
                    <template #actions>
                        <span id="content-alt_text-count" class="text-xs text-muted-foreground tabular"
                            >{{ formatNumber(altText.length) }}/{{ formatNumber(altField?.max ?? 0) }}</span
                        >
                    </template>
                    <template #default="{ describedBy, invalid }">
                        <Textarea
                            id="content-alt_text"
                            v-model="altText"
                            :rows="altField?.rows"
                            :maxlength="altField?.max"
                            :readonly="readonly"
                            :aria-describedby="`${describedBy ?? ''} content-alt_text-count`.trim()"
                            :aria-invalid="invalid"
                            :class="readonly ? 'bg-muted' : ''"
                        />
                    </template>
                </FormField>

                <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                        <template v-if="!readonly">
                            <!-- The native control stays the real input (keyboard, screen readers, tests); the label is what people see. -->
                            <input
                                id="content-image"
                                ref="input"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="peer sr-only"
                                :aria-describedby="imageDescribedBy"
                                :aria-invalid="errors.image ? true : undefined"
                                @change="choose"
                            />
                            <label
                                for="content-image"
                                class="inline-flex h-11 cursor-pointer items-center justify-center gap-2 rounded-md border bg-background px-4 text-sm font-medium peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring hover:bg-accent"
                            >
                                <ImageUp class="size-4" aria-hidden="true" />{{ asset ? 'Ganti gambar' : 'Unggah gambar' }}
                            </label>
                        </template>
                        <p v-if="asset && !preview" class="text-xs text-muted-foreground" data-testid="media-meta">
                            {{ asset.width }}×{{ asset.height }} · {{ formatFileSize(asset.sizeBytes) }} ·
                            {{ asset.mimeType.replace('image/', '').toUpperCase() }}
                        </p>
                    </div>
                    <p v-if="file" class="text-xs break-words">Dipilih: {{ file.name }}. Simpan untuk mengunggahnya.</p>
                    <p v-if="!readonly" id="content-image-hint" class="text-xs text-muted-foreground">{{ imageRequirements }}</p>
                    <p v-if="errors.image" id="content-image-error" role="alert" class="text-xs font-medium text-danger">{{ errors.image }}</p>
                    <p v-if="!readonly" id="content-image-warning" class="text-xs font-medium text-warning" aria-live="polite">{{ warning }}</p>
                </div>

                <div v-if="visualDirection" class="space-y-1 text-sm" data-testid="visual-direction">
                    <p class="text-xs font-semibold text-muted-foreground">Arahan visual <span class="font-normal">· ubah di Lainnya → Detail kreatif</span></p>
                    <p class="line-clamp-3 break-words whitespace-pre-line">{{ visualDirection }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
