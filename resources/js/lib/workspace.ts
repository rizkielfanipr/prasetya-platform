import type { TransactionWorkspaceView } from '@/types/backoffice';

/** The Transaksi workspace views in tab order (ADR-0010). */
export const workspaceViews: { value: TransactionWorkspaceView; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'attention', label: 'Perlu Ditinjau' },
    { value: 'events', label: 'Event Pembayaran' },
];
