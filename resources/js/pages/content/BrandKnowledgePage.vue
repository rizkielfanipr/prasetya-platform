<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import PanelCard from '@/components/layout/PanelCard.vue';
import FormField from '@/components/shared/FormField.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import ContentNav from '@/features/content/ContentNav.vue';
import BrandKnowledgeAssistantDialog from '@/features/content/brand/BrandKnowledgeAssistantDialog.vue';
import { routes } from '@/lib/routes';
import type { AuditEntry } from '@/types/backoffice';
import type { BrandKnowledgeVersion } from '@/types/content';
import { router, useForm } from '@inertiajs/vue3';
import { BookOpenText, Lightbulb, ShieldCheck } from '@lucide/vue';
import { computed, watch } from 'vue';

/** One human-facing Brand Knowledge document; the server versions and chunks it for retrieval (ADR-0011). */
const props = defineProps<{
    brand: { id: string; code: string; name: string };
    brands: { id: string; code: string; name: string }[];
    knowledge: BrandKnowledgeVersion | null;
    versions: BrandKnowledgeVersion[];
    activity: AuditEntry[];
    assistantDraft: { text: string; nonce: string } | null;
}>();

const form = useForm({ source_text: props.knowledge?.sourceText ?? '' });
const characters = computed(() => form.source_text.length);

watch(
    () => props.assistantDraft?.nonce,
    () => {
        if (props.assistantDraft) form.source_text = props.assistantDraft.text;
    },
    { immediate: true },
);

function save(): void {
    form.put(routes.contentBrandKnowledge(props.brand.id), {
        preserveScroll: true,
        onSuccess: () => form.defaults({ source_text: form.source_text }),
    });
}

function applyAssistantDraft(text: string): void {
    form.source_text = text;
}
</script>

<template>
    <AppLayout title="Content Studio" description="Satu sumber pengetahuan brand yang dicari AI saat membantu membuat konten.">
        <ContentNav current="brands" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold">Brand Knowledge</h2>
                <p class="text-sm text-muted-foreground">{{ brand.name }}</p>
            </div>
            <label v-if="brands.length > 1" class="flex items-center gap-2 text-sm font-semibold">
                Brand
                <select
                    :value="brand.id"
                    class="h-9 rounded-md border bg-card px-2 text-sm font-normal"
                    @change="router.get(routes.contentBrand(($event.target as HTMLSelectElement).value))"
                >
                    <option v-for="option in brands" :key="option.id" :value="option.id">{{ option.name }}</option>
                </select>
            </label>
        </div>

        <PanelCard title="Ceritakan brand Anda">
            <form class="space-y-5" aria-label="Brand Knowledge" novalidate @submit.prevent="save">
                <Alert class="border-info/30 bg-info-surface text-foreground [&>svg]:text-info">
                    <Lightbulb aria-hidden="true" />
                    <AlertTitle>Panduan mengisi Brand Knowledge</AlertTitle>
                    <AlertDescription class="mt-1.5 gap-3">
                        <p>Tulis bebas tentang audiens, gaya komunikasi, layanan, harga, proses kerja, aturan, FAQ, dan informasi penting lainnya.</p>
                        <div class="grid w-full gap-2 sm:grid-cols-2">
                            <div class="flex gap-2 rounded-md border bg-card/70 p-3">
                                <BookOpenText class="mt-0.5 size-4 shrink-0 text-info" aria-hidden="true" />
                                <p>AI mencari bagian yang relevan dari dokumen ini ketika membantu membuat draft konten.</p>
                            </div>
                            <div class="flex gap-2 rounded-md border bg-card/70 p-3">
                                <ShieldCheck class="mt-0.5 size-4 shrink-0 text-success" aria-hidden="true" />
                                <p>Gunakan informasi yang sudah disetujui. Jangan masukkan data pelanggan, password, API key, atau rahasia.</p>
                            </div>
                        </div>
                    </AlertDescription>
                </Alert>
                <FormField
                    id="brand-knowledge"
                    label="Brand Knowledge *"
                    :error="form.errors.source_text"
                    hint="Gunakan paragraf dan judul sederhana agar knowledge mudah dicari. Maksimal 50.000 karakter."
                >
                    <template #actions>
                        <BrandKnowledgeAssistantDialog :brand-id="brand.id" :current-source-text="form.source_text" @generated="applyAssistantDraft" />
                    </template>
                    <template #default="{ describedBy, invalid }">
                        <Textarea
                            id="brand-knowledge"
                            v-model="form.source_text"
                            :rows="24"
                            maxlength="50000"
                            class="min-h-[34rem] text-sm leading-6"
                            :aria-describedby="describedBy"
                            :aria-invalid="invalid"
                            placeholder="Contoh: Kami membantu pemilik UMKM membuat website. Gaya komunikasi kami ramah dan mudah dipahami. Paket layanan..."
                        />
                    </template>
                </FormField>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                    <p class="text-xs text-muted-foreground" aria-live="polite">
                        {{ characters.toLocaleString('id-ID') }}/50.000 karakter
                        <template v-if="knowledge"> · versi {{ knowledge.version }} · {{ knowledge.chunks }} bagian pencarian</template>
                    </p>
                    <Button type="submit" :disabled="form.processing || !form.isDirty" :aria-busy="form.processing">
                        {{ form.processing ? 'Menyimpan…' : 'Simpan dan proses knowledge' }}
                    </Button>
                </div>
            </form>
        </PanelCard>
    </AppLayout>
</template>
