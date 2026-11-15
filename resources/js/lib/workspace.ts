import type { TransactionWorkspaceView } from '@/types/backoffice';

/** The Transaksi workspace views in tab order (ADR-0016). The webhook log lives in Pengaturan → Log Gateway. */
export const workspaceViews: { value: TransactionWorkspaceView; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'attention', label: 'Perlu Ditinjau' },
];
