export const BUSINESS_TIMEZONE = 'Asia/Jakarta';
const LOCALE = 'id-ID';
const EMPTY = '—';

const currency = new Intl.NumberFormat(LOCALE, { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 0 });
const number = new Intl.NumberFormat(LOCALE);
const date = new Intl.DateTimeFormat(LOCALE, { day: 'numeric', month: 'short', year: 'numeric', timeZone: BUSINESS_TIMEZONE });
const dateTime = new Intl.DateTimeFormat(LOCALE, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: BUSINESS_TIMEZONE,
});
const shortDay = new Intl.DateTimeFormat(LOCALE, { day: 'numeric', month: 'short', timeZone: 'UTC' });

/** Rp18.450.000, the format docs/design.md requires. Intl puts a no-break space after Rp. */
export function formatCurrency(amount: number | null | undefined): string {
    return amount === null || amount === undefined ? EMPTY : currency.format(amount).replace(/\s/gu, '');
}

export function formatNumber(value: number): string {
    return number.format(value);
}

export function formatDate(iso: string | null | undefined): string {
    return iso ? date.format(new Date(iso)) : EMPTY;
}

export function formatDateTime(iso: string | null | undefined): string {
    return iso ? `${dateTime.format(new Date(iso)).replace('.', ':')} WIB` : EMPTY;
}

const time = new Intl.DateTimeFormat(LOCALE, { hour: '2-digit', minute: '2-digit', timeZone: BUSINESS_TIMEZONE });

/** 10:42 WIB, the time of day in business time. */
export function formatTime(iso: string | null | undefined): string {
    return iso ? `${time.format(new Date(iso)).replace('.', ':')} WIB` : EMPTY;
}

/** A calendar day from the server (YYYY-MM-DD); already in business time, so it must not shift. */
export function formatDay(day: string): string {
    return shortDay.format(new Date(`${day}T00:00:00Z`));
}

/** 081234567890 → 0812-3456-7890. Unknown shapes come back unchanged rather than mangled. */
export function formatPhone(phone: string | null | undefined): string {
    if (!phone) {
        return EMPTY;
    }

    const digits = phone.replace(/[^\d+]/g, '');
    const match = /^(\+?\d{2,4})(\d{4})(\d{3,5})$/.exec(digits);

    return match ? `${match[1]}-${match[2]}-${match[3]}` : phone;
}

/** Splits PW-202611-000128 for display that must not wrap awkwardly. */
export function formatInvoice(invoice: string | null | undefined): string {
    return invoice ?? EMPTY;
}

export function formatChange(current: number, previous: number): { direction: 'up' | 'down' | 'flat'; label: string } {
    if (current === previous) {
        return { direction: 'flat', label: '0%' };
    }

    if (previous === 0) {
        return { direction: 'up', label: 'Baru' };
    }

    const percent = Math.round(((current - previous) / previous) * 100);

    return { direction: percent >= 0 ? 'up' : 'down', label: `${percent >= 0 ? '+' : ''}${percent}%` };
}
