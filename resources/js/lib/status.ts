import type { OrderStatus, PaymentStatus, ReconciliationGroup, WebhookStatus } from '@/types/backoffice';
import { Ban, CheckCircle2, Circle, Clock, type LucideIcon, RefreshCw, ShieldAlert, ShieldCheck, TriangleAlert, XCircle } from '@lucide/vue';

export type StatusTone = 'success' | 'warning' | 'danger' | 'info' | 'neutral';

export interface StatusDefinition {
    label: string;
    tone: StatusTone;
    icon: LucideIcon;
}

export const toneClasses: Record<StatusTone, string> = {
    success: 'bg-success-surface text-success',
    warning: 'bg-warning-surface text-warning',
    danger: 'bg-danger-surface text-danger',
    info: 'bg-info-surface text-info',
    neutral: 'bg-neutral-surface text-neutral',
};

export const paymentStatuses: Record<PaymentStatus, StatusDefinition> = {
    CREATED: { label: 'Dibuat', tone: 'neutral', icon: Circle },
    PENDING: { label: 'Menunggu', tone: 'warning', icon: Clock },
    PAID: { label: 'Berhasil', tone: 'success', icon: CheckCircle2 },
    FAILED: { label: 'Gagal', tone: 'danger', icon: XCircle },
    EXPIRED: { label: 'Kedaluwarsa', tone: 'danger', icon: XCircle },
    CANCELLED: { label: 'Dibatalkan', tone: 'neutral', icon: Ban },
};

export const orderStatuses: Record<OrderStatus, StatusDefinition> = {
    DRAFT: { label: 'Draf', tone: 'neutral', icon: Circle },
    PENDING_PAYMENT: { label: 'Menunggu bayar', tone: 'warning', icon: Clock },
    CONFIRMED: { label: 'Terkonfirmasi', tone: 'success', icon: CheckCircle2 },
    CANCELLED: { label: 'Dibatalkan', tone: 'neutral', icon: Ban },
    EXPIRED: { label: 'Kedaluwarsa', tone: 'danger', icon: XCircle },
};

export const webhookStatuses: Record<WebhookStatus, StatusDefinition> = {
    RECEIVED: { label: 'Diterima', tone: 'info', icon: RefreshCw },
    VERIFIED: { label: 'Terverifikasi', tone: 'info', icon: ShieldCheck },
    PROCESSED: { label: 'Diproses', tone: 'success', icon: CheckCircle2 },
    IGNORED: { label: 'Diabaikan', tone: 'neutral', icon: Circle },
    DUPLICATE: { label: 'Duplikat', tone: 'info', icon: RefreshCw },
    REJECTED: { label: 'Ditolak', tone: 'danger', icon: ShieldAlert },
    FAILED: { label: 'Gagal diproses', tone: 'danger', icon: TriangleAlert },
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
    'content.submitted': 'Diajukan untuk ditinjau',
    'content.revision_requested': 'Revisi diminta',
    'content.approved': 'Disetujui',
    'content.rejected': 'Ditolak',
    'content.published': 'Ditandai dipublikasikan',
    'content.ai_model_selected': 'Model AI diganti',
    'content.generation_requested': 'Draft AI diminta',
    'content.generation_applied': 'Draft AI diterapkan',
    'content.generation_failed': 'Draft AI gagal',
    'content.brand_rules_updated': 'Aturan brand diubah',
    'content.pillar_created': 'Pilar ditambahkan',
    'content.pillar_updated': 'Pilar diubah',
    'content.campaign_created': 'Kampanye ditambahkan',
    'content.campaign_updated': 'Kampanye diubah',
    'content.fact_added': 'Fakta ditambahkan',
    'content.fact_retired': 'Fakta tidak berlaku lagi',
};

/** Back-office user status (Pengguna). */
export const userStatuses: Record<'active' | 'inactive', StatusDefinition> = {
    active: { label: 'Aktif', tone: 'success', icon: CheckCircle2 },
    inactive: { label: 'Nonaktif', tone: 'neutral', icon: Ban },
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
