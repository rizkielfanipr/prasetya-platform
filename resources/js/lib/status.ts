import type { OrderStatus, PaymentStatus, ReconciliationGroup, WebhookStatus } from '@/types/backoffice';
import {
    ArrowPathIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    MinusCircleIcon,
    NoSymbolIcon,
    ShieldCheckIcon,
    ShieldExclamationIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import type { Component } from 'vue';

export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

export interface StatusDefinition {
    /** Status labels are English (ADR-0014); everything around them stays Indonesian. */
    label: string;
    tone: StatusTone;
    /** Heroicons on migrated screens; Content Studio still passes Lucide icons until it moves (ADR-0014). */
    icon: Component;
}

export const toneClasses: Record<StatusTone, string> = {
    success: 'bg-success-surface text-success',
    warning: 'bg-warning-surface text-warning',
    danger: 'bg-danger-surface text-danger',
    info: 'bg-info-surface text-info',
    neutral: 'bg-neutral-surface text-neutral',
};

export const paymentStatuses: Record<PaymentStatus, StatusDefinition> = {
    CREATED: { label: 'Created', tone: 'neutral', icon: MinusCircleIcon },
    PENDING: { label: 'Pending', tone: 'warning', icon: ClockIcon },
    PAID: { label: 'Paid', tone: 'success', icon: CheckCircleIcon },
    FAILED: { label: 'Failed', tone: 'danger', icon: XCircleIcon },
    EXPIRED: { label: 'Expired', tone: 'danger', icon: XCircleIcon },
    CANCELLED: { label: 'Cancelled', tone: 'neutral', icon: NoSymbolIcon },
};

/** Shown where a transaction has no payment attempt yet; it is the absence of a status, worded like one. */
export const noPaymentLabel = 'No Payment';

export const orderStatuses: Record<OrderStatus, StatusDefinition> = {
    DRAFT: { label: 'Draft', tone: 'neutral', icon: MinusCircleIcon },
    PENDING_PAYMENT: { label: 'Pending Payment', tone: 'warning', icon: ClockIcon },
    CONFIRMED: { label: 'Confirmed', tone: 'success', icon: CheckCircleIcon },
    CANCELLED: { label: 'Cancelled', tone: 'neutral', icon: NoSymbolIcon },
    EXPIRED: { label: 'Expired', tone: 'danger', icon: XCircleIcon },
};

export const webhookStatuses: Record<WebhookStatus, StatusDefinition> = {
    RECEIVED: { label: 'Received', tone: 'info', icon: ArrowPathIcon },
    VERIFIED: { label: 'Verified', tone: 'info', icon: ShieldCheckIcon },
    PROCESSED: { label: 'Processed', tone: 'success', icon: CheckCircleIcon },
    IGNORED: { label: 'Ignored', tone: 'neutral', icon: MinusCircleIcon },
    DUPLICATE: { label: 'Duplicate', tone: 'info', icon: ArrowPathIcon },
    REJECTED: { label: 'Rejected', tone: 'danger', icon: ShieldExclamationIcon },
    FAILED: { label: 'Failed', tone: 'danger', icon: ExclamationTriangleIcon },
};

export const rejectionLabels: Record<string, string> = {
    SOURCE_NOT_ALLOWED: 'Alamat pengirim tidak diizinkan',
    MISSING_HEADERS: 'Data tanda tangan tidak lengkap',
    INVALID_SIGNATURE: 'Tanda tangan tidak valid',
    CLIENT_MISMATCH: 'Kode merchant tidak cocok',
    MALFORMED_PAYLOAD: 'Isi notifikasi tidak dapat dibaca',
    UNKNOWN_INVOICE: 'Invoice tidak dikenal',
    AMOUNT_MISMATCH: 'Jumlah tidak cocok',
    INVALID_TRANSITION: 'Perubahan status tidak diizinkan',
    UNSUPPORTED_STATUS: 'Status gateway belum didukung',
    DUPLICATE: 'Pengiriman ulang',
};

export const eventTypeLabels: Record<string, string> = {
    ATTEMPT_CREATED: 'Percobaan pembayaran dibuat',
    CHECKOUT_OPENED: 'Checkout dibuka',
    CHECKOUT_FAILED: 'Checkout gagal dibuat',
    STATUS_CHANGED: 'Status berubah',
    EVIDENCE_IGNORED: 'Bukti gateway diabaikan',
    EVIDENCE_REJECTED: 'Bukti gateway ditolak',
    GATEWAY_CHECKED: 'Status diperiksa ke gateway',
};

export const sourceLabels: Record<string, string> = {
    API: 'API',
    GATEWAY_NOTIFICATION: 'Notifikasi gateway',
    GATEWAY_CHECK: 'Cek status gateway',
    SCHEDULER: 'Terjadwal',
    ADMIN: 'Admin',
};

/** Audit actions written by the platform. Unknown actions fall back to their code. */
export const auditActionLabels: Record<string, string> = {
    'auth.login': 'Masuk',
    'auth.logout': 'Keluar',
    'user.created': 'Pengguna ditambahkan',
    'user.updated': 'Nama pengguna diubah',
    'user.activated': 'Pengguna diaktifkan',
    'user.deactivated': 'Pengguna dinonaktifkan',
    'user.password_reset': 'Kata sandi diatur oleh admin',
    'user.password_changed': 'Kata sandi diganti sendiri',
    'user.profile_updated': 'Profil diperbarui',
    'order.note_added': 'Catatan admin ditambahkan',
    'payment.paid': 'Pembayaran tercatat lunas',
    'payment.gateway_checked': 'Status diperiksa ke gateway',
    'package.activated': 'Paket diaktifkan',
    'package.deactivated': 'Paket dinonaktifkan',
    'content.created': 'Konten dibuat',
    'content.revised': 'Konten diubah',
    'content.details_updated': 'Judul atau jadwal diubah',
    'content.submitted': 'Diajukan untuk ditinjau (alur lama)',
    'content.revision_requested': 'Revisi diminta',
    'content.approved': 'Disetujui',
    'content.rejected': 'Ditolak',
    'content.published': 'Ditandai sudah diposting',
    'content.ai_model_selected': 'Model AI diganti',
    'content.marked_ready': 'Ditandai siap posting',
    'content.returned_to_draft': 'Dikembalikan ke Draft',
    'content.archived': 'Diarsipkan',
    'content.insight_recorded': 'Insight dicatat',
    'content.research_requested': 'Riset web diminta',
    'content.research_completed': 'Riset web selesai',
    'content.research_failed': 'Riset web gagal',
    'content.source_added': 'Sumber manual ditambahkan',
    'content.source_cited': 'Sumber dipakai',
    'content.source_removed': 'Sumber dilepas',
    'content.generation_requested': 'Draft AI diminta',
    'content.generation_applied': 'Draft AI diterapkan',
    'content.generation_failed': 'Draft AI gagal',
    'content.brand_rules_updated': 'Aturan brand diubah',
    'content.brand_knowledge_updated': 'Brand Knowledge diperbarui',
    'content.brand_knowledge_assist_requested': 'Bantuan AI Brand Knowledge diminta',
    'content.brand_knowledge_assist_succeeded': 'Usulan Brand Knowledge dari AI selesai',
    'content.brand_knowledge_assist_failed': 'Bantuan AI Brand Knowledge gagal',
    'content.pillar_created': 'Pilar ditambahkan',
    'content.pillar_updated': 'Pilar diubah',
    'content.campaign_created': 'Campaign ditambahkan',
    'content.campaign_updated': 'Campaign diubah',
    'content.fact_added': 'Fakta ditambahkan',
    'content.fact_retired': 'Fakta tidak berlaku lagi',
};

/** Back-office user status (Pengguna). */
export const userStatuses: Record<'active' | 'inactive', StatusDefinition> = {
    active: { label: 'Aktif', tone: 'success', icon: CheckCircleIcon },
    inactive: { label: 'Nonaktif', tone: 'neutral', icon: NoSymbolIcon },
};

export const reconciliationGroups: Record<ReconciliationGroup, { label: string; description: string }> = {
    orders_without_attempt: { label: 'Pesanan tanpa percobaan bayar', description: 'Pesanan masih menunggu, tetapi checkout belum pernah dibuat.' },
    orders_without_success: {
        label: 'Pesanan tanpa pembayaran berhasil',
        description: 'Sudah ada percobaan bayar, belum ada yang berhasil, dan sudah melewati ambang waktu.',
    },
    unmatched_payments: { label: 'Pembayaran tanpa pesanan', description: 'Notifikasi terverifikasi menyebut invoice yang tidak dikenal platform.' },
    amount_mismatch: { label: 'Jumlah tidak cocok', description: 'Notifikasi terverifikasi berisi jumlah yang berbeda dari tagihan. Status tidak diubah.' },
    rejected_webhooks: { label: 'Webhook ditolak', description: 'Notifikasi gagal verifikasi, misalnya tanda tangan tidak valid.' },
    duplicate_webhooks: { label: 'Webhook duplikat', description: 'Pengiriman ulang yang sudah ditangani aman. Hanya untuk inspeksi.' },
    pending_too_long: { label: 'Menunggu terlalu lama', description: 'Pembayaran masih terbuka melewati ambang waktu.' },
    paid_inconsistent: {
        label: 'Status pesanan tidak konsisten',
        description: 'Dibayar tetapi pesanan belum terkonfirmasi, terkonfirmasi tanpa bayar, atau dibayar lebih dari sekali.',
    },
    invalid_transitions: { label: 'Transisi tidak valid', description: 'Bukti gateway meminta perubahan status yang tidak diizinkan atau belum didukung.' },
};
