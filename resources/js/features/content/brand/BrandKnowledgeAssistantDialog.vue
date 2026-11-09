<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { routes } from '@/lib/routes';
import { useForm } from '@inertiajs/vue3';
import { CheckCircle2, LoaderCircle, Sparkles } from '@lucide/vue';
import { computed, onBeforeUnmount, ref } from 'vue';

type AnswerKey = 'overview' | 'audience' | 'offers' | 'differentiators' | 'voice' | 'visual' | 'rules' | 'operations';
type Answers = Record<AnswerKey, string>;

const props = defineProps<{ brandId: string; currentSourceText: string }>();
const emit = defineEmits<{ generated: [text: string] }>();
const open = ref(false);
const processState = ref<'idle' | 'processing' | 'completed' | 'failed'>('idle');
const processError = ref<string | null>(null);
const elapsedSeconds = ref(0);
let elapsedTimer: number | null = null;
let closeTimer: number | null = null;
const questions: { key: AnswerKey; label: string; hint: string; placeholder: string }[] = [
    {
        key: 'overview',
        label: 'Apa yang dilakukan brand ini?',
        hint: 'Ceritakan identitas, bidang usaha, tujuan, wilayah layanan, dan hal terpenting tentang brand.',
        placeholder: 'Contoh: Kami membantu UMKM membuat website yang mudah dikelola...',
    },
    {
        key: 'audience',
        label: 'Siapa audiens utama dan masalah mereka?',
        hint: 'Sebutkan tipe pelanggan, kebutuhan, kekhawatiran, dan hasil yang mereka cari.',
        placeholder: 'Contoh: Pemilik UMKM yang belum memiliki tim teknologi...',
    },
    {
        key: 'offers',
        label: 'Apa produk, layanan, harga, dan ketentuannya?',
        hint: 'Masukkan hanya penawaran dan harga yang sudah disetujui. Jelaskan apa yang termasuk atau tidak termasuk.',
        placeholder: 'Contoh: Paket landing page mulai Rp... termasuk..., belum termasuk...',
    },
    {
        key: 'differentiators',
        label: 'Apa keunggulan dan bukti yang boleh disebutkan?',
        hint: 'Tuliskan pembeda, pengalaman, proses, portofolio, atau pencapaian yang dapat dibuktikan.',
        placeholder: 'Contoh: Pendampingan menggunakan bahasa nonteknis dan revisi...',
    },
    {
        key: 'voice',
        label: 'Bagaimana gaya komunikasi brand?',
        hint: 'Jelaskan nada, sapaan, istilah yang dianjurkan, CTA, hashtag, serta contoh kalimat yang terasa tepat.',
        placeholder: 'Contoh: Ramah, profesional, ringkas; gunakan sapaan Anda...',
    },
    {
        key: 'visual',
        label: 'Bagaimana arahan visualnya?',
        hint: 'Jelaskan warna, tipografi, penggunaan logo, karakter gambar, dan gaya yang perlu dihindari.',
        placeholder: 'Contoh: Bersih dan modern, dominan..., hindari foto stok yang...',
    },
    {
        key: 'rules',
        label: 'Apa klaim, kata, dan topik yang dilarang?',
        hint: 'Masukkan larangan, batas promosi, informasi sensitif, dan hal yang harus selalu diperiksa manusia.',
        placeholder: 'Contoh: Jangan menjanjikan ranking pertama; jangan menyebut harga lama...',
    },
    {
        key: 'operations',
        label: 'Apa proses, kebijakan, dan FAQ penting?',
        hint: 'Ceritakan alur kerja, waktu pengerjaan, pembayaran, revisi, dukungan, pembatalan, dan pertanyaan umum.',
        placeholder: 'Contoh: Pengerjaan dimulai setelah data lengkap dan uang muka diterima...',
    },
];
const emptyAnswers = (): Answers => ({ overview: '', audience: '', offers: '', differentiators: '', voice: '', visual: '', rules: '', operations: '' });
const form = useForm<{ answers: Answers; current_source_text: string }>({ answers: emptyAnswers(), current_source_text: props.currentSourceText });
const hasAnswer = computed(() => Object.values(form.answers).some((answer) => answer.trim() !== ''));
const elapsedLabel = computed(() => {
    const minutes = Math.floor(elapsedSeconds.value / 60);
    const seconds = elapsedSeconds.value % 60;

    return minutes > 0 ? `${minutes} menit ${seconds.toString().padStart(2, '0')} detik` : `${seconds} detik`;
});

function record(value: unknown): Record<string, unknown> | null {
    return typeof value === 'object' && value !== null && !Array.isArray(value) ? (value as Record<string, unknown>) : null;
}

function resultFrom(pageProps: unknown): { text: string | null; error: string | null } {
    const propsRecord = record(pageProps);
    const draft = record(propsRecord?.assistantDraft);
    const flash = record(propsRecord?.flash);

    return {
        text: typeof draft?.text === 'string' && draft.text.trim() !== '' ? draft.text : null,
        error: typeof flash?.error === 'string' && flash.error.trim() !== '' ? flash.error : null,
    };
}

function startTimer(): void {
    stopTimer();
    elapsedSeconds.value = 0;
    elapsedTimer = window.setInterval(() => (elapsedSeconds.value += 1), 1000);
}

function stopTimer(): void {
    if (elapsedTimer !== null) window.clearInterval(elapsedTimer);
    elapsedTimer = null;
}

function finishWithDraft(text: string): void {
    processState.value = 'completed';
    emit('generated', text);
    closeTimer = window.setTimeout(() => {
        open.value = false;
        form.reset();
        processState.value = 'idle';
    }, 900);
}

function generate(): void {
    form.current_source_text = props.currentSourceText;
    processError.value = null;
    form.post(routes.contentBrandKnowledgeAssist(props.brandId), {
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            processState.value = 'processing';
            startTimer();
        },
        onSuccess: (page) => {
            const result = resultFrom(page.props);
            if (result.text !== null) {
                finishWithDraft(result.text);

                return;
            }
            processState.value = 'failed';
            processError.value = result.error ?? 'AI selesai tanpa menghasilkan Brand Knowledge. Coba lagi atau pilih model lain.';
        },
        onError: (errors) => {
            processState.value = 'failed';
            processError.value = Object.values(errors).find((error): error is string => typeof error === 'string') ?? 'Jawaban belum dapat diproses.';
        },
        onFinish: stopTimer,
    });
}

onBeforeUnmount(() => {
    stopTimer();
    if (closeTimer !== null) window.clearTimeout(closeTimer);
});
</script>

<template>
    <AlertDialog v-model:open="open">
        <AlertDialogTrigger as-child>
            <Button type="button" variant="outline" class="gap-2"><Sparkles class="size-4" aria-hidden="true" />Bantuan AI</Button>
        </AlertDialogTrigger>
        <AlertDialogContent class="max-h-[calc(100vh-2rem)] overflow-hidden sm:max-w-3xl">
            <AlertDialogHeader>
                <AlertDialogTitle>Susun Brand Knowledge dengan AI</AlertDialogTitle>
                <AlertDialogDescription>
                    Jawab yang Anda ketahui. AI akan merapikannya menjadi usulan di editor; tidak ada versi yang tersimpan sampai Anda memeriksa dan
                    menyimpannya sendiri.
                </AlertDialogDescription>
            </AlertDialogHeader>

            <div v-if="processState === 'processing' || processState === 'completed'" class="space-y-6 py-8" aria-live="polite" aria-atomic="true">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <LoaderCircle v-if="processState === 'processing'" class="size-7 animate-spin" aria-hidden="true" />
                    <CheckCircle2 v-else class="size-7" aria-hidden="true" />
                </div>
                <div class="space-y-2 text-center">
                    <h3 class="font-bold">{{ processState === 'processing' ? 'AI sedang mengolah jawaban' : 'Brand Knowledge berhasil disusun' }}</h3>
                    <p class="text-sm text-muted-foreground">
                        {{
                            processState === 'processing'
                                ? 'Modal ini akan tetap terbuka sampai provider mengirim hasil. Jangan tutup atau memuat ulang halaman.'
                                : 'Hasil sudah dimasukkan ke editor. Modal akan ditutup otomatis.'
                        }}
                    </p>
                </div>
                <ol class="mx-auto max-w-md space-y-3 rounded-lg border bg-muted/30 p-4 text-sm">
                    <li class="flex items-center gap-3"><CheckCircle2 class="size-5 text-success" aria-hidden="true" />Jawaban siap dan permintaan dikirim</li>
                    <li class="flex items-center gap-3">
                        <LoaderCircle v-if="processState === 'processing'" class="size-5 animate-spin text-primary" aria-hidden="true" />
                        <CheckCircle2 v-else class="size-5 text-success" aria-hidden="true" />
                        AI menyusun panduan operasional
                    </li>
                    <li class="flex items-center gap-3" :class="processState === 'completed' ? '' : 'text-muted-foreground'">
                        <CheckCircle2 v-if="processState === 'completed'" class="size-5 text-success" aria-hidden="true" />
                        <span v-else class="size-5 rounded-full border" aria-hidden="true" />
                        Hasil dimasukkan ke Brand Knowledge
                    </li>
                </ol>
                <p class="text-center text-xs text-muted-foreground">Waktu berjalan: {{ elapsedLabel }}</p>
            </div>

            <div v-else class="max-h-[60vh] space-y-5 overflow-y-auto pr-2">
                <div
                    v-if="processState === 'failed' && processError"
                    role="alert"
                    class="rounded-lg border border-danger/30 bg-danger/10 p-3 text-sm text-danger"
                >
                    <strong>AI belum berhasil mengolah jawaban.</strong><br />{{ processError }} Jawaban Anda tetap tersimpan di formulir ini.
                </div>
                <div class="rounded-lg border bg-muted/30 p-3 text-sm text-muted-foreground">
                    Jangan masukkan data pelanggan, password, API key, atau rahasia. Pertanyaan boleh dikosongkan jika belum ada jawabannya.
                </div>
                <div v-for="question in questions" :key="question.key" class="space-y-2">
                    <label :for="`brand-assistant-${question.key}`" class="text-sm font-semibold">{{ question.label }}</label>
                    <p :id="`brand-assistant-${question.key}-hint`" class="text-xs text-muted-foreground">{{ question.hint }}</p>
                    <Textarea
                        :id="`brand-assistant-${question.key}`"
                        v-model="form.answers[question.key]"
                        :aria-describedby="`brand-assistant-${question.key}-hint`"
                        :placeholder="question.placeholder"
                        maxlength="5000"
                        :rows="3"
                    />
                </div>
                <p v-if="form.errors.answers" role="alert" class="text-sm font-medium text-danger">{{ form.errors.answers }}</p>
            </div>

            <AlertDialogFooter v-if="processState !== 'processing' && processState !== 'completed'">
                <AlertDialogCancel :disabled="form.processing">Batal</AlertDialogCancel>
                <Button type="button" :disabled="form.processing || !hasAnswer" :aria-busy="form.processing" @click="generate">
                    {{ processState === 'failed' ? 'Coba olah lagi' : 'Olah jawaban dengan AI' }}
                </Button>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
