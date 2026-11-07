/** Sections of the transaction detail page (ADR-0010). Kept in the URL as ?tab=, default summary. */
export type TransactionDetailTab = 'summary' | 'payments' | 'activity';

const tabs: readonly TransactionDetailTab[] = ['summary', 'payments', 'activity'];

export const transactionDetailTabs = {
    all: tabs,
    /** Unknown or missing values fall back to the summary instead of failing. */
    fromUrl(url: string): TransactionDetailTab {
        const value = new URL(url, 'http://localhost').searchParams.get('tab');

        return tabs.find((tab) => tab === value) ?? 'summary';
    },
};
