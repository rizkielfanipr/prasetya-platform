import type { Brand } from '@/types/backoffice';

export const brands: Record<
    Brand,
    { label: string; fullName: string; badgeClass: string; dotClass: string; tagline: string; logo: { src: string; width: number; height: number } }
> = {
    WEBWORKS: {
        label: 'Webworks',
        fullName: 'Prasetya Webworks',
        badgeClass: 'bg-webworks-surface text-webworks-text',
        dotClass: 'bg-webworks',
        tagline: 'Jasa Pembuatan Website',
        logo: { src: '/images/brands/prasetya-webworks-logo-white.svg', width: 211, height: 64 },
    },
    SATAMU: {
        label: 'Satamu',
        fullName: 'Satamu Invitation',
        badgeClass: 'bg-satamu-surface text-satamu-text',
        dotClass: 'bg-satamu',
        tagline: 'Undangan & Momen Spesial',
        logo: { src: '/images/brands/satamu-logo-white.svg', width: 2400, height: 900 },
    },
};

export const brandCodes = Object.keys(brands) as Brand[];
