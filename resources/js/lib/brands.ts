import type { Brand } from '@/types/backoffice';

export const brands: Record<
    Brand,
    {
        label: string;
        fullName: string;
        /** Two-letter line code on the brand bullet, like a commuter line's letter. */
        code: string;
        /**
         * The brand bullet. Orange fills mean live state, so the two brands differ by form, not by shade:
         * Webworks is a solid derived-orange plate, Satamu an outline in its own coral (DESIGN.md).
         */
        bulletClass: string;
        badgeClass: string;
        dotClass: string;
        tagline: string;
        /** Logo for light backgrounds, copied unchanged from the brand's own site. */
        logo: { src: string; width: number; height: number };
        /** The Satamu file carries wide padding around its mark, so it needs more height to read at the same size. */
        logoClass: string;
    }
> = {
    WEBWORKS: {
        label: 'Webworks',
        fullName: 'Prasetya Webworks',
        code: 'WW',
        bulletClass: 'border-[1.5px] border-tint-700 bg-tint-700 text-white',
        badgeClass: 'bg-webworks-surface text-webworks-text',
        dotClass: 'bg-webworks',
        tagline: 'Jasa Pembuatan Website',
        logo: { src: '/images/brands/prasetya-webworks-logo.svg', width: 211, height: 64 },
        logoClass: 'h-7',
    },
    SATAMU: {
        label: 'Satamu',
        fullName: 'Satamu Invitation',
        code: 'ST',
        bulletClass: 'border-[1.5px] border-satamu bg-card text-satamu-text',
        badgeClass: 'bg-satamu-surface text-satamu-text',
        dotClass: 'bg-satamu',
        tagline: 'Undangan & Momen Spesial',
        logo: { src: '/images/brands/satamu-logo.svg', width: 2400, height: 900 },
        logoClass: '-ml-2 h-12',
    },
};

export const brandCodes = Object.keys(brands) as Brand[];
