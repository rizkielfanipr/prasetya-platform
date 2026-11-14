/**
 * Duitku payment method codes in words. Only what docs/api/duitku-contract.md confirms is named: SP, NQ, and SQ
 * are QRIS codes, BC is a virtual account. The issuer behind each code is not documented, so the code stays visible.
 * Unknown codes fall back to the code itself rather than a guess.
 */
const methodLabels: Record<string, string> = {
    SP: 'QRIS (SP)',
    NQ: 'QRIS (NQ)',
    SQ: 'QRIS (SQ)',
    BC: 'Virtual Account (BC)',
};

export function paymentMethodLabel(code: string | null | undefined): string | null {
    if (!code) {
        return null;
    }

    return methodLabels[code] ?? code;
}
