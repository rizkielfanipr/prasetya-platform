<script setup lang="ts">
import FormField from '@/components/shared/FormField.vue';
import { Button } from '@/components/ui/button';
import SeparatorDot from '@/components/shared/SeparatorDot.vue';
import { Input } from '@/components/ui/input';
import { routes } from '@/lib/routes';
import type { ContentItem, ResearchSource } from '@/types/content';
import { ArrowTopRightOnSquareIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

/**
 * Referensi (ADR-0017): links this Draft relies on, added by hand. The server checks every URL against the SSRF policy
 * and reads a missing title itself; AI reads the references as untrusted background, never as facts.
 */
const props = defineProps<{ item: ContentItem; references: ResearchSource[]; readonly: boolean }>();

const adding = ref(false);
const form = useForm({ url: '', title: '', note: '' });
const removing = ref<string | null>(null);

function add(): void {
    form.transform((data) => ({ url: data.url, title: data.title || null, note: data.note || null })).post(routes.contentSources(props.item.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            adding.value = false;
        },
    });
}

function remove(reference: ResearchSource): void {
    removing.value = reference.id;
    router.delete(routes.contentSource(props.item.id, reference.id), { preserveScroll: true, onFinish: () => (removing.value = null) });
}
</script>

<template>
    <section aria-labelledby="references-heading" class="space-y-3" data-testid="content-references">
        <div>
            <h3 id="references-heading" class="text-sm font-extrabold">Referensi</h3>
            <p class="text-[13px] text-muted-foreground">Link yang dipakai untuk konten ini. AI membacanya sebagai bahan latar, bukan sebagai fakta.</p>
        </div>

        <ul v-if="references.length" class="divide-y divide-border">
            <li v-for="reference in references" :key="reference.id" class="flex items-start justify-between gap-3 py-2.5 first:pt-0">
                <div class="min-w-0">
                    <a
                        :href="reference.url"
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        class="inline-flex items-center gap-1 text-sm font-bold break-words text-primary-text underline decoration-tint-300 decoration-2 underline-offset-4 hover:decoration-tint-600"
                        >{{ reference.title }}<ArrowTopRightOnSquareIcon class="size-3.5 shrink-0 stroke-2" aria-hidden="true" /><span class="sr-only">
                            (tab baru)</span
                        ></a
                    >
                    <p class="text-xs text-muted-foreground">
                        {{ reference.domain }}<template v-if="reference.note"><SeparatorDot />{{ reference.note }}</template>
                    </p>
                </div>
                <Button
                    v-if="!readonly"
                    variant="ghost"
                    class="size-11 shrink-0"
                    :disabled="removing === reference.id"
                    :aria-label="`Hapus referensi ${reference.title}`"
                    @click="remove(reference)"
                >
                    <XMarkIcon class="size-4 stroke-2" aria-hidden="true" />
                </Button>
            </li>
        </ul>
        <p v-else class="text-[13px] text-muted-foreground">Belum ada referensi.</p>

        <template v-if="!readonly">
            <form v-if="adding" class="space-y-3 rounded-xl bg-tint-50 p-3 md:p-4" @submit.prevent="add">
                <FormField id="reference-url" v-slot="{ describedBy, invalid }" label="URL *" :error="form.errors.url">
                    <Input
                        id="reference-url"
                        v-model="form.url"
                        type="url"
                        inputmode="url"
                        class="h-11"
                        required
                        :aria-describedby="describedBy"
                        :aria-invalid="invalid"
                    />
                </FormField>
                <FormField
                    id="reference-title"
                    v-slot="{ describedBy, invalid }"
                    label="Judul"
                    hint="Kosongkan untuk diambil dari halamannya."
                    :error="form.errors.title"
                >
                    <Input id="reference-title" v-model="form.title" maxlength="300" class="h-11" :aria-describedby="describedBy" :aria-invalid="invalid" />
                </FormField>
                <FormField id="reference-note" v-slot="{ describedBy, invalid }" label="Catatan" :error="form.errors.note">
                    <Input id="reference-note" v-model="form.note" maxlength="500" class="h-11" :aria-describedby="describedBy" :aria-invalid="invalid" />
                </FormField>
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" class="h-11" :disabled="form.processing || !form.url">{{
                        form.processing ? 'Menambahkan…' : 'Tambah referensi'
                    }}</Button>
                    <Button type="button" variant="ghost" class="h-11" @click="((adding = false), form.reset(), form.clearErrors())">Batal</Button>
                </div>
            </form>
            <Button v-else type="button" variant="outline" class="h-11 gap-2 border-input" data-testid="add-reference" @click="adding = true">
                <PlusIcon class="size-4 stroke-2" aria-hidden="true" />Tambah referensi
            </Button>
        </template>
    </section>
</template>
