<script setup lang="ts">
import EmptyState from '@/components/shared/EmptyState.vue';
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { formatBrandGuide, previewBrandGuide } from '@/lib/brand-guide';
import { brandListRules } from '@/lib/content';
import { copy } from '@/lib/copy';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { BrandRules, BrandRulesVersion } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';

/**
 * A brand's rules: read-only by default, editable as a whole, saved as a new version with a change note. Earlier
 * versions stay listed, because submitted revisions name the version they met.
 */
const props = defineProps<{ brandId: string; rules: BrandRules | null; versions: BrandRulesVersion[] }>();
const editing = ref(false);

const texts = [
    { key: 'audience', name: 'audience', label: 'Audiens', hint: 'Untuk siapa brand ini berbicara.' },
    { key: 'voice', name: 'voice', label: 'Gaya bahasa', hint: 'Nada dan cara menyapa.' },
    { key: 'visualGuidance', name: 'visual_guidance', label: 'Arahan visual', hint: 'Warna, tipografi, logo, dan gaya gambar.' },
] as const;
function initial(): { guide: string; change_note: string } {
    return { guide: formatBrandGuide(props.rules), change_note: '' };
}

const form = useForm(initial());
const preview = computed(() => previewBrandGuide(form.guide));

/** "maks. 600 karakter", "10–30 hashtag", or "—" when the brand sets no limit. */
function limit(min: number | null, max: number | null, unit: string): string {
    if (min !== null && max !== null) return `${min}–${max} ${unit}`;
    if (max !== null) return `maks. ${max} ${unit}`;
    if (min !== null) return `min. ${min} ${unit}`;

    return 'tanpa batas';
}

function start(): void {
    form.defaults(initial());
    form.reset();
    form.clearErrors();
    editing.value = true;
}

function save(): void {
    form.put(routes.contentBrandRules(props.brandId), { preserveScroll: true, onSuccess: () => (editing.value = false) });
}
</script>

<template>
    <div class="space-y-6">
        <template v-if="!editing">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-muted-foreground">
                    <template v-if="rules"
                        >Versi {{ rules.version }} · {{ rules.createdBy }} · {{ formatDateTime(rules.createdAt) }} — “{{ rules.changeNote }}”</template
                    >
                    <template v-else>Belum ada aturan. Konten tetap bisa dibuat; pemeriksaan aturan menunggu aturan diisi.</template>
                </p>
                <Button class="gap-2" @click="start"><Pencil class="size-4" aria-hidden="true" />{{ rules ? 'Ubah aturan' : 'Isi aturan' }}</Button>
            </div>
            <EmptyState
                v-if="!rules"
                title="Aturan brand belum diisi"
                description="Isi dengan data yang sudah disetujui pemilik brand. Jangan menebak harga atau klaim."
            />
            <dl v-else class="grid gap-x-8 gap-y-5 md:grid-cols-2" data-testid="brand-rules">
                <div v-for="field in texts" :key="field.key">
                    <dt class="text-xs font-semibold text-muted-foreground">{{ field.label }}</dt>
                    <dd class="mt-1 text-sm break-words whitespace-pre-line">{{ rules[field.key] ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-muted-foreground">Batas</dt>
                    <dd class="mt-1 space-y-0.5 text-sm">
                        <p>Caption: {{ limit(rules.captionMinLength, rules.captionMaxLength, 'karakter') }}</p>
                        <p>Hashtag: {{ limit(rules.hashtagMin, rules.hashtagMax, 'hashtag') }}</p>
                        <p>Jarak topik sama: {{ rules.topicRepeatDays ? `${rules.topicRepeatDays} hari` : '—' }}</p>
                    </dd>
                </div>
                <div v-for="field in brandListRules" :key="field.key">
                    <dt class="text-xs font-semibold text-muted-foreground">{{ field.label }}</dt>
                    <dd class="mt-1 text-sm">
                        <ul v-if="rules[field.key].length" class="list-inside list-disc space-y-0.5 break-words">
                            <li v-for="entry in rules[field.key]" :key="entry">{{ entry }}</li>
                        </ul>
                        <span v-else>—</span>
                    </dd>
                </div>
            </dl>
        </template>

        <form v-else class="space-y-5" novalidate aria-label="Ubah aturan brand" @submit.prevent="save">
            <div class="rounded-lg border bg-muted/30 p-4 text-sm">
                <p class="font-semibold">Satu panduan, tetap dapat divalidasi</p>
                <p class="mt-1 text-muted-foreground">
                    Isi di bawah heading yang tersedia. Satu baris menjadi satu aturan untuk daftar kata, klaim, CTA, hashtag, dan contoh. Biarkan nilai setelah
                    titik dua kosong jika batas tidak digunakan.
                </p>
            </div>
            <FormField
                id="rules-guide"
                v-slot="{ describedBy, invalid }"
                label="Panduan brand *"
                :error="form.errors.guide"
                hint="Heading dalam kurung siku tidak boleh dihapus atau diganti. Maksimal 30.000 karakter."
            >
                <Textarea
                    id="rules-guide"
                    v-model="form.guide"
                    :rows="30"
                    maxlength="30000"
                    class="font-mono text-sm leading-6"
                    :aria-describedby="describedBy"
                    :aria-invalid="invalid"
                    data-testid="brand-guide-editor"
                />
            </FormField>

            <section class="rounded-lg border p-4" aria-labelledby="guide-preview-title" aria-live="polite">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 id="guide-preview-title" class="text-sm font-semibold">Ringkasan yang terbaca</h3>
                    <span :class="preview.valid ? 'text-success' : 'text-danger'" class="text-xs font-semibold">
                        {{ preview.valid ? 'Format siap disimpan' : `${preview.errors.length} masalah format` }}
                    </span>
                </div>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3" data-testid="brand-guide-summary">
                    <div>
                        <dt class="text-xs text-muted-foreground">Bagian dasar terisi</dt>
                        <dd class="font-semibold">{{ preview.filledBasics }}/3</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Kata terlarang</dt>
                        <dd class="font-semibold">{{ preview.forbiddenTerms }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Klaim terlarang</dt>
                        <dd class="font-semibold">{{ preview.forbiddenClaims }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">CTA disetujui</dt>
                        <dd class="font-semibold">{{ preview.allowedCtas }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Hashtag wajib</dt>
                        <dd class="font-semibold">{{ preview.requiredHashtags }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Hashtag terlarang</dt>
                        <dd class="font-semibold">{{ preview.forbiddenHashtags }}</dd>
                    </div>
                </dl>
                <ul v-if="preview.errors.length" class="mt-3 list-inside list-disc space-y-1 text-sm text-danger">
                    <li v-for="error in preview.errors.slice(0, 5)" :key="error">{{ error }}</li>
                </ul>
            </section>
            <FormField
                id="rules-change_note"
                v-slot="{ describedBy, invalid }"
                label="Alasan perubahan *"
                :error="form.errors.change_note"
                hint="Tercatat di riwayat versi dan audit."
            >
                <Input id="rules-change_note" v-model="form.change_note" class="h-11" maxlength="500" :aria-describedby="describedBy" :aria-invalid="invalid" />
            </FormField>
            <div class="flex flex-wrap justify-end gap-3 border-t pt-4">
                <Button type="button" variant="outline" :disabled="form.processing" @click="editing = false">{{ copy.cancel }}</Button>
                <Button type="submit" :disabled="form.processing || !preview.valid" :aria-busy="form.processing">{{
                    form.processing ? copy.saving : 'Simpan sebagai versi baru'
                }}</Button>
            </div>
        </form>

        <section v-if="versions.length" aria-labelledby="rules-versions" class="space-y-2 border-t pt-4">
            <h3 id="rules-versions" class="text-sm font-semibold">Riwayat versi</h3>
            <ol class="space-y-1 text-sm">
                <li v-for="version in versions" :key="version.version">
                    <span class="font-semibold">Versi {{ version.version }}</span> · {{ version.createdBy }} · {{ formatDateTime(version.createdAt) }} —
                    {{ version.changeNote }}
                </li>
            </ol>
        </section>
    </div>
</template>
