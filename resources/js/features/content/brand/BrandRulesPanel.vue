<script setup lang="ts">
import EmptyState from '@/components/shared/EmptyState.vue';
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { brandListRules, linesToList } from '@/lib/content';
import { copy } from '@/lib/copy';
import { formatDateTime } from '@/lib/format';
import { routes } from '@/lib/routes';
import type { BrandRules, BrandRulesVersion } from '@/types/content';
import { useForm } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { ref } from 'vue';

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
const numbers = [
    { key: 'captionMinLength', name: 'caption_min_length', label: 'Caption minimal (karakter)', hint: 'Kurang dari ini: peringatan.', max: 2200 },
    { key: 'captionMaxLength', name: 'caption_max_length', label: 'Caption maksimal (karakter)', hint: 'Lebih dari ini: pelanggaran.', max: 2200 },
    { key: 'hashtagMin', name: 'hashtag_min', label: 'Hashtag minimal', hint: 'Caption dan kolom hashtag dihitung bersama.', max: 30 },
    { key: 'hashtagMax', name: 'hashtag_max', label: 'Hashtag maksimal', hint: 'Lebih dari ini: pelanggaran.', max: 30 },
    {
        key: 'topicRepeatDays',
        name: 'topic_repeat_days',
        label: 'Jarak topik yang sama (hari)',
        hint: 'Topik sama lebih dekat dari ini: peringatan.',
        max: 365,
    },
] as const;

type TextName = (typeof texts)[number]['name'];
type NumberName = (typeof numbers)[number]['name'];
type ListName = (typeof brandListRules)[number]['name'];

function initial(): Record<TextName | NumberName | ListName | 'change_note', string> {
    const r = props.rules;
    const values = { change_note: '' } as Record<TextName | NumberName | ListName | 'change_note', string>;
    for (const field of texts) values[field.name] = r?.[field.key] ?? '';
    for (const field of numbers) values[field.name] = r?.[field.key] === null || r?.[field.key] === undefined ? '' : String(r[field.key]);
    for (const field of brandListRules) values[field.name] = (r?.[field.key] ?? []).join('\n');

    return values;
}

const form = useForm(initial());

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
    form.transform((data) => {
        const payload: Record<string, unknown> = { ...data };
        for (const field of brandListRules) payload[field.name] = linesToList(data[field.name]);
        for (const field of numbers) payload[field.name] = data[field.name] === '' ? null : Number(data[field.name]);

        return payload;
    }).put(routes.contentBrandRules(props.brandId), { preserveScroll: true, onSuccess: () => (editing.value = false) });
}

/** Errors for list fields may come back per entry (forbidden_terms.3); show them on the field. */
function listError(name: string): string | undefined {
    const errors = form.errors as Record<string, string | undefined>;

    return errors[name] ?? Object.entries(errors).find(([key]) => key.startsWith(`${name}.`))?.[1];
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
            <div class="grid gap-4 md:grid-cols-3">
                <FormField
                    v-for="field in texts"
                    :id="`rules-${field.name}`"
                    :key="field.name"
                    v-slot="{ describedBy, invalid }"
                    :label="field.label"
                    :error="form.errors[field.name]"
                    :hint="field.hint"
                >
                    <Textarea
                        :id="`rules-${field.name}`"
                        v-model="form[field.name]"
                        :rows="3"
                        :maxlength="2000"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <FormField
                    v-for="field in numbers"
                    :id="`rules-${field.name}`"
                    :key="field.name"
                    v-slot="{ describedBy, invalid }"
                    :label="field.label"
                    :error="form.errors[field.name]"
                    :hint="field.hint"
                >
                    <Input
                        :id="`rules-${field.name}`"
                        v-model="form[field.name]"
                        type="number"
                        min="0"
                        :max="field.max"
                        inputmode="numeric"
                        class="h-11"
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <FormField
                    v-for="field in brandListRules"
                    :id="`rules-${field.name}`"
                    :key="field.name"
                    v-slot="{ describedBy, invalid }"
                    :label="field.label"
                    :error="listError(field.name)"
                    :hint="field.hint"
                >
                    <Textarea :id="`rules-${field.name}`" v-model="form[field.name]" :rows="4" :aria-describedby="describedBy" :aria-invalid="invalid" />
                </FormField>
            </div>
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
                <Button type="submit" :disabled="form.processing" :aria-busy="form.processing">{{
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
