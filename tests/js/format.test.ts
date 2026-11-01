import { formatChange, formatCurrency, formatDate, formatDateTime, formatDay, formatPhone } from '@/lib/format';
import { describe, expect, it } from 'vitest';

describe('format', () => {
    it('formats rupiah as the design system requires', () => {
        expect(formatCurrency(18450000)).toBe('Rp18.450.000');
        expect(formatCurrency(0)).toBe('Rp0');
        expect(formatCurrency(null)).toBe('—');
    });

    it('shows dates in the business timezone', () => {
        // 18:00 UTC on 1 Oct is 01:00 on 2 Oct in Jakarta.
        expect(formatDate('2026-10-01T18:00:00Z')).toBe('2 Okt 2026');
        expect(formatDateTime('2026-10-01T18:00:00Z')).toContain('WIB');
        expect(formatDateTime(null)).toBe('—');
    });

    it('does not shift a server calendar day', () => {
        expect(formatDay('2026-11-08')).toBe('8 Nov');
    });

    it('formats phones and leaves unknown shapes alone', () => {
        expect(formatPhone('081234567890')).toBe('0812-3456-7890');
        expect(formatPhone('12')).toBe('12');
        expect(formatPhone(null)).toBe('—');
    });

    it('describes change between periods', () => {
        expect(formatChange(110, 100)).toEqual({ direction: 'up', label: '+10%' });
        expect(formatChange(90, 100)).toEqual({ direction: 'down', label: '-10%' });
        expect(formatChange(5, 5)).toEqual({ direction: 'flat', label: '0%' });
        expect(formatChange(5, 0)).toEqual({ direction: 'up', label: 'Baru' });
    });
});
