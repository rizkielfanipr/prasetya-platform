<script setup lang="ts">
import AppLayout from '@/components/layout/AppLayout.vue';
import FormField from '@/components/shared/FormField.vue';
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import ContentNav from '@/features/content/ContentNav.vue';
import BrandKnowledgeAssistantDialog from '@/features/content/brand/BrandKnowledgeAssistantDialog.vue';
import { formatNumber } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { AuditEntry } from '@/types/backoffice';
import type { BrandKnowledgeVersion } from '@/types/content';
import { CheckCircleIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
import { router, useForm } from '@inertiajs/vue3';
import { computed, nextTick, watch } from 'vue';

/**
 * One brand document (ADR-0017). The server versions and chunks it for AI retrieval; its `## Aturan Posting` section
 * holds the posting rules, read when the document is saved. Without that section the rules in force stay as they
 * are, and the page offers to insert them so they move into the document unchanged.
 */
const props = defineProps<{
    brand: { id: string; code: string; name: string };
    brands: { id: string; code: string; name: string }[];
    knowledge: BrandKnowledgeVersion | null;
    versions: BrandKnowledgeVersion[];
    activity: AuditEntry[];
    assistantDraft: { text: string; nonce: string } | null;
    rules: { version: number | null; inDocument: boolean; section: string };
}>();

const form = useForm({ source_text: props.knowledge?.sourceText ?? '' });
const characters = computed(() => form.source_text.length);
// Whether the text being edited already holds the section, so the offer disappears once it is inserted.
const hasSection = computed(() => /^#{1,6}\s+aturan posting\s*#*\s*$/imu.test(form.source_text));

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

/** Appends the rules in force as the section; nothing is saved until the person saves the document. */
async function insertRules(): Promise<void> {
    form.source_text = `${form.source_text.trimEnd()}\n\n${props.rules.section}\n`;
    await nextTick();
    const field = document.getElementById('brand-knowledge') as HTMLTextAreaElement | null;
    if (field) {
        field.focus();
        field.setSelectionRange(field.value.length, field.value.length);
        field.scrollTop = field.scrollHeight;
    }
}
</script>

<template>
    <AppLayout title="Content Studio" description="Satu dokumen brand: pengetahuan yang dibaca AI dan aturan yang diperiksa sebelum posting.">
        <ContentNav current="brands" />

        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-sm font-extrabold">Brand Knowledge</h2>
                <p class="text-[13px] text-muted-foreground">{{ brand.name }}</p>
            </div>
            <div v-if="brands.length > 1" class="flex flex-col gap-1.5">
                <label for="brand-switch" class="text-[11px] font-extrabold tracking-[0.07em] text-muted-foreground uppercase">Brand</label>
                <select
                    id="brand-switch"
                    :value="brand.id"
                    class="h-11 rounded-md border border-input bg-card px-3 text-sm font-semibold"
                    @change="router.get(routes.contentBrand(($event.target as HTMLSelectElement).value))"
                >
                    <option v-for="option in brands" :key="option.id" :value="option.id">{{ option.name }}</option>
                </select>
            </div>
        </div>

        <section aria-labelledby="rules-heading" class="rounded-xl bg-tint-50 px-5 py-4 md:px-7" data-testid="posting-rules">
            <h3 id="rules-heading" class="text-sm font-extrabold">Aturan Posting</h3>
            <p v-if="hasSection" class="mt-1 flex items-start gap-2 text-[13px]">
                <CheckCircleIcon class="mt-0.5 size-4 shrink-0 stroke-2 text-success" aria-hidden="true" />
                <span
                    >Aturan ada di bagian <b>## Aturan Posting</b> dokumen ini dan dibaca saat disimpan<template v-if="rules.version"
                        >. Yang berlaku sekarang: versi {{ rules.version }}</template
                    >.</span
                >
            </p>
            <template v-else>
                <p class="mt-1 flex items-start gap-2 text-[13px]">
                    <InformationCircleIcon class="mt-0.5 size-4 shrink-0 stroke-2" aria-hidden="true" />
                    <span v-if="rules.version"
                        >Aturan versi {{ rules.version }} berlaku, tetapi belum tertulis di dokumen ini. Sisipkan agar bisa diubah di sini; isinya sama
                        persis.</span
                    >
                    <span v-else>Belum ada aturan posting. Sisipkan bagiannya, lalu isi kata dan klaim terlarang, CTA, hashtag, dan batas caption.</span>
                </p>
                <Button type="button" variant="outline" class="mt-3 h-11 border-input" data-testid="insert-rules" @click="insertRules"
                    >Sisipkan aturan yang berlaku</Button
                >
            </template>
            <details class="mt-3 text-[13px]">
                <summary class="cursor-pointer font-bold text-primary-text">Format bagian Aturan Posting</summary>
                <p class="mt-2 text-muted-foreground">
                    Tulis judul <code>## Aturan Posting</code>, lalu blok <code>[BATAS]</code>, <code>[KATA TERLARANG]</code>, <code>[KLAIM TERLARANG]</code>,
                    <code>[CTA DISETUJUI]</code>, <code>[HASHTAG WAJIB]</code>, dan <code>[HASHTAG TERLARANG]</code>, satu entri per baris. Di
                    <code>[BATAS]</code> pakai label <i>Caption minimal</i>, <i>Caption maksimal</i>, <i>Hashtag minimal</i>, <i>Hashtag maksimal</i>, dan
                    <i>Jarak topik (hari)</i>, misalnya <code>Caption maksimal: 700</code>.
                </p>
            </details>
        </section>

        <form class="space-y-4" aria-label="Brand Knowledge" novalidate @submit.prevent="save">
            <FormField
                id="brand-knowledge"
                label="Dokumen brand *"
                :error="form.errors.source_text"
                hint="Tulis bebas: audiens, gaya bahasa, layanan, harga, proses kerja, dan FAQ. Harga dan angka di caption harus tertulis di sini. Jangan masukkan data pelanggan, password, atau API key."
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
                    />
                </template>
            </FormField>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4">
                <p class="text-xs text-muted-foreground tabular" aria-live="polite">
                    {{ formatNumber(characters) }}/50.000 karakter<template v-if="knowledge"
                        ><SeparatorDot />versi {{ knowledge.version }}<SeparatorDot />{{ knowledge.chunks }} bagian pencarian</template
                    >
                </p>
                <Button type="submit" class="h-11" :disabled="form.processing || !form.isDirty" :aria-busy="form.processing">
                    {{ form.processing ? 'Menyimpan…' : 'Simpan dokumen' }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
