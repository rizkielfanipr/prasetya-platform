import type { StatusDefinition } from '@/lib/status';
import { BUSINESS_TIMEZONE } from '@/lib/format';
import type {
    BrandTab,
    CampaignPriority,
    ContentAction,
    ContentDecision,
    ContentFields,
    ContentRevision,
    ContentStatus,
    CopyField,
    GenerationStatus,
    PostingStep,
    ResearchStatus,
    RevisionArea,
    RuleSeverity,
    SourceTrust,
} from '@/types/content';
import { Ban, CheckCircle2, Circle, Clock, type LucideIcon, Pencil, Send, TriangleAlert, XCircle } from '@lucide/vue';

/** Each line of a textarea as one list entry; blank lines dropped. */
export function linesToList(text: string): string[] {
    return text
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');
}

/** Content item statuses (ADR-0011, data-dictionary.md). */
export const contentStatuses: Record<ContentStatus, StatusDefinition> = {
    DRAFT: { label: 'Draft', tone: 'neutral', icon: Circle },
    READY_FOR_REVIEW: { label: 'Menunggu tinjauan', tone: 'info', icon: Clock },
    REVISION_REQUESTED: { label: 'Perlu revisi', tone: 'warning', icon: Pencil },
    APPROVED: { label: 'Disetujui', tone: 'success', icon: CheckCircle2 },
    PUBLISHED: { label: 'Dipublikasikan', tone: 'success', icon: Send },
    REJECTED: { label: 'Ditolak', tone: 'danger', icon: XCircle },
};

export const contentStatusOrder: readonly ContentStatus[] = ['DRAFT', 'READY_FOR_REVIEW', 'REVISION_REQUESTED', 'APPROVED', 'PUBLISHED', 'REJECTED'];

export const contentDecisions: Record<ContentDecision, { label: string; icon: LucideIcon }> = {
    APPROVED: { label: 'Disetujui', icon: CheckCircle2 },
    REVISION_REQUESTED: { label: 'Revisi diminta', icon: Pencil },
    REJECTED: { label: 'Ditolak', icon: Ban },
};

export const contentActionLabels: Record<ContentAction, string> = {
    edit: 'Simpan konten',
    editDetails: 'Ubah rencana',
    submit: 'Ajukan untuk ditinjau',
    requestRevision: 'Minta revisi',
    approve: 'Setujui',
    reject: 'Tolak',
    markPublished: 'Tandai sudah dipublikasikan',
};

/** The posting checklist, in order. The server requires every step (PostingQueue::CHECKLIST). */
export const postingSteps: readonly { value: PostingStep; label: string }[] = [
    { value: 'image', label: 'Gambar dari paket ini sudah diunggah ke Instagram' },
    { value: 'caption', label: 'Caption dan hashtag ditempel apa adanya' },
    { value: 'alt_text', label: 'Alt text diisi di Pengaturan lanjutan Instagram' },
    { value: 'account', label: 'Diposting dari akun brand yang benar' },
];

/** The approved Instagram metrics, in form order. Reach is required. */
export const insightMetrics = [
    { key: 'reach', name: 'reach', label: 'Jangkauan', required: true },
    { key: 'views', name: 'views', label: 'Tayangan', required: false },
    { key: 'likes', name: 'likes', label: 'Suka', required: false },
    { key: 'comments', name: 'comments', label: 'Komentar', required: false },
    { key: 'saves', name: 'saves', label: 'Simpan', required: false },
    { key: 'shares', name: 'shares', label: 'Bagikan', required: false },
    { key: 'profileVisits', name: 'profile_visits', label: 'Kunjungan profil', required: false },
    { key: 'follows', name: 'follows', label: 'Pengikut baru', required: false },
] as const;

/** 0.0917 → "9,2%"; null → "—". */
export function formatRate(rate: number | null | undefined): string {
    return rate === null || rate === undefined ? '—' : `${(rate * 100).toFixed(1).replace('.', ',')}%`;
}

/** "Terlambat 2 hari", "Hari ini", or nothing. */
export function dueLabel(state: 'overdue' | 'today' | 'upcoming' | null, daysLate = 0): string | null {
    if (state === 'overdue') return daysLate > 0 ? `Terlambat ${daysLate} hari` : 'Terlambat';
    if (state === 'today') return 'Jadwal hari ini';

    return null;
}

/** Content Studio sections. */
export const contentSections = [
    { value: 'calendar', label: 'Kalender' },
    { value: 'to-post', label: 'Siap posting' },
    { value: 'items', label: 'Konten' },
    { value: 'insights', label: 'Insight' },
    { value: 'brands', label: 'Brand Knowledge' },
    { value: 'settings', label: 'Pengaturan AI' },
] as const;

export const sourceTrusts: Record<SourceTrust, StatusDefinition> = {
    OFFICIAL: { label: 'Resmi', tone: 'success', icon: CheckCircle2 },
    GENERAL: { label: 'Umum', tone: 'neutral', icon: Circle },
    USER_GENERATED: { label: 'Konten pengguna', tone: 'warning', icon: TriangleAlert },
};

export const researchStatuses: Record<ResearchStatus, StatusDefinition> = {
    QUEUED: { label: 'Menunggu antrean', tone: 'info', icon: Clock },
    RUNNING: { label: 'Sedang mencari', tone: 'info', icon: Clock },
    SUCCEEDED: { label: 'Selesai', tone: 'success', icon: CheckCircle2 },
    FAILED: { label: 'Gagal', tone: 'danger', icon: XCircle },
};

export const revisionAreas: readonly { value: RevisionArea; label: string }[] = [
    { value: 'caption', label: 'Caption' },
    { value: 'hashtags', label: 'Hashtag' },
    { value: 'alt_text', label: 'Alt text' },
    { value: 'visual_direction', label: 'Arahan visual' },
    { value: 'image', label: 'Gambar' },
    { value: 'brief', label: 'Brief' },
];

export const areaLabels = (areas: readonly string[]): string =>
    areas.map((area) => revisionAreas.find((option) => option.value === area)?.label ?? area).join(', ');

export const copyFields: readonly { value: CopyField; label: string }[] = [
    { value: 'caption', label: 'Caption' },
    { value: 'hashtags', label: 'Hashtag' },
    { value: 'alt_text', label: 'Alt text' },
    { value: 'visual_direction', label: 'Arahan visual' },
];

export const generationStatuses: Record<GenerationStatus, StatusDefinition> = {
    QUEUED: { label: 'Menunggu antrean', tone: 'info', icon: Clock },
    RUNNING: { label: 'AI sedang menulis', tone: 'info', icon: Clock },
    SUCCEEDED: { label: 'Draft dibuat', tone: 'success', icon: CheckCircle2 },
    FAILED: { label: 'Gagal', tone: 'danger', icon: XCircle },
    DISCARDED: { label: 'Tidak diterapkan', tone: 'neutral', icon: Ban },
};

/** $0.00123 for small AI costs; cents are not enough. */
export function formatUsd(amount: number): string {
    return `$${amount < 0.01 && amount > 0 ? amount.toFixed(5) : amount.toFixed(2)}`;
}

export const brandTabs: readonly { value: BrandTab; label: string }[] = [
    { value: 'rules', label: 'Panduan Brand' },
    { value: 'pillars', label: 'Strategi Konten' },
    { value: 'facts', label: 'Knowledge Base' },
    { value: 'activity', label: 'Aktivitas' },
];

export const contentFactCategories = {
    GENERAL: 'Umum',
    SERVICE: 'Layanan',
    PRICE: 'Harga',
    PROCESS: 'Proses kerja',
    ADVANTAGE: 'Keunggulan',
    FAQ: 'FAQ',
    PROMO: 'Promo',
    POLICY: 'Kebijakan',
    EVIDENCE: 'Bukti atau pencapaian',
} as const;

export const campaignPriorities: Record<CampaignPriority, string> = { HIGH: 'Tinggi', NORMAL: 'Normal', LOW: 'Rendah' };

export const ruleSeverities: Record<RuleSeverity, StatusDefinition> = {
    ERROR: { label: 'Pelanggaran', tone: 'danger', icon: XCircle },
    WARNING: { label: 'Peringatan', tone: 'warning', icon: TriangleAlert },
};

/** The list rules of a brand, in form order. Each line of the textarea is one entry. */
export const brandListRules = [
    {
        key: 'forbiddenTerms',
        name: 'forbidden_terms',
        label: 'Kata terlarang',
        hint: 'Satu per baris. Ditemukan sebagai kata utuh di caption, hashtag, dan alt text: pelanggaran.',
    },
    { key: 'forbiddenClaims', name: 'forbidden_claims', label: 'Klaim terlarang', hint: 'Satu per baris, misalnya "dijamin ranking 1". Pelanggaran.' },
    { key: 'allowedCtas', name: 'allowed_ctas', label: 'CTA yang disetujui', hint: 'Satu per baris. Caption tanpa salah satunya mendapat peringatan.' },
    { key: 'requiredHashtags', name: 'required_hashtags', label: 'Hashtag wajib', hint: 'Satu per baris, dengan atau tanpa #. Yang belum ada: peringatan.' },
    { key: 'forbiddenHashtags', name: 'forbidden_hashtags', label: 'Hashtag terlarang', hint: 'Satu per baris. Pelanggaran.' },
    { key: 'preferredTerms', name: 'preferred_terms', label: 'Kata yang dianjurkan', hint: 'Satu per baris. Panduan untuk penulis; tidak diperiksa.' },
    { key: 'approvedExamples', name: 'approved_examples', label: 'Contoh caption yang disetujui', hint: 'Satu per baris. Panduan gaya.' },
    { key: 'rejectedExamples', name: 'rejected_examples', label: 'Contoh caption yang ditolak', hint: 'Satu per baris, sebaiknya dengan alasannya.' },
] as const;

export type ContentField = keyof ContentFields;

/** The editable fields, in form order, with their server limits (SaveContentRevisionRequest). */
export const contentFields: readonly { key: ContentField; name: string; label: string; hint: string; max: number; rows: number; required: boolean }[] = [
    { key: 'objective', name: 'objective', label: 'Tujuan', hint: 'Apa yang ingin dicapai posting ini.', max: 1000, rows: 2, required: false },
    { key: 'audience', name: 'audience', label: 'Audiens', hint: 'Untuk siapa posting ini.', max: 1000, rows: 2, required: false },
    { key: 'brief', name: 'brief', label: 'Brief', hint: 'Pesan utama dan isi gambar. Wajib sebelum diajukan.', max: 5000, rows: 4, required: true },
    { key: 'caption', name: 'caption', label: 'Caption', hint: 'Teks posting Instagram. Wajib sebelum diajukan.', max: 2200, rows: 6, required: true },
    { key: 'hashtags', name: 'hashtags', label: 'Hashtag', hint: 'Pisahkan dengan spasi, maksimal 30.', max: 1000, rows: 2, required: false },
    {
        key: 'altText',
        name: 'alt_text',
        label: 'Alt text',
        hint: 'Deskripsi gambar untuk pembaca layar. Wajib sebelum diajukan.',
        max: 1000,
        rows: 2,
        required: true,
    },
    {
        key: 'visualDirection',
        name: 'visual_direction',
        label: 'Arahan visual',
        hint: 'Warna, tata letak, dan gaya gambar.',
        max: 3000,
        rows: 3,
        required: false,
    },
];

export const imageRequirements = 'JPEG, PNG, atau WebP · rasio 4:5 · minimal 1080×1350 piksel · maksimal 8 MB';

export function hashtagCount(value: string | null | undefined): number {
    return value ? (value.match(/#[^\s#]+/gu) ?? []).length : 0;
}

/** Fields that differ between two revisions, plus whether the image changed. */
export function revisionChanges(before: ContentRevision, after: ContentRevision): { fields: ContentField[]; image: boolean } {
    return {
        fields: contentFields.map((field) => field.key).filter((key) => (before[key] ?? '') !== (after[key] ?? '')),
        image: (before.asset?.checksum ?? null) !== (after.asset?.checksum ?? null),
    };
}

const localParts = new Intl.DateTimeFormat('en-CA', {
    timeZone: BUSINESS_TIMEZONE,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
});

/** An instant as the value of a datetime-local input in business time (YYYY-MM-DDTHH:mm). */
export function toLocalInput(iso: string | Date): string {
    const parts = Object.fromEntries(localParts.formatToParts(typeof iso === 'string' ? new Date(iso) : iso).map((part) => [part.type, part.value]));

    return `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`;
}

const time = new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: BUSINESS_TIMEZONE, hourCycle: 'h23' });
const weekday = new Intl.DateTimeFormat('id-ID', { weekday: 'long', timeZone: 'UTC' });
const weekdayShort = new Intl.DateTimeFormat('id-ID', { weekday: 'short', timeZone: 'UTC' });
const dayMonth = new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'long', timeZone: 'UTC' });

/** 10:00 WIB */
export function formatTime(iso: string): string {
    return `${time.format(new Date(iso)).replace('.', ':')} WIB`;
}

/** A business-time day (YYYY-MM-DD) as "Senin, 9 November" or, short, "Sen 9". */
export function formatWeekday(day: string, short = false): string {
    const date = new Date(`${day}T00:00:00Z`);

    return short ? `${weekdayShort.format(date)} ${date.getUTCDate()}` : `${weekday.format(date)}, ${dayMonth.format(date)}`;
}

export function formatFileSize(bytes: number): string {
    return bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}
