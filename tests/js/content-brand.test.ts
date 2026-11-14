import BrandRulesPanel from '@/features/content/brand/BrandRulesPanel.vue';
import ContentComposition from '@/features/content/ContentComposition.vue';
import { formatBrandGuide, previewBrandGuide } from '@/lib/brand-guide';
import { linesToList } from '@/lib/content';
import type { BrandRules } from '@/types/content';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';

describe('linesToList', () => {
    it('keeps one trimmed entry per non-blank line', () => {
        expect(linesToList(' murah \n\n termurah\n')).toEqual(['murah', 'termurah']);
    });
});

describe('ContentComposition', () => {
    it('states each pillar as count, share, and target in text', () => {
        const wrapper = mount(ContentComposition, {
            props: {
                entries: [
                    {
                        brand: { code: 'WEBWORKS', name: 'Prasetya Webworks' },
                        total: 4,
                        unassigned: 1,
                        pillars: [
                            { id: 'a', name: 'Edukasi', targetShare: 60, count: 2 },
                            { id: 'b', name: 'Promo', targetShare: 40, count: 1 },
                        ],
                    },
                ],
            },
        });

        expect(wrapper.get('[data-testid="composition-Edukasi"]').text()).toContain('2 · 50% / target 60%');
        expect(wrapper.get('[data-testid="composition-Promo"]').text()).toContain('1 · 25% / target 40%');
        expect(wrapper.text()).toContain('(4 konten, 1 tanpa pilar)');
    });
});

describe('BrandRulesPanel', () => {
    const rules: BrandRules = {
        version: 3,
        audience: 'Pemilik UMKM',
        voice: 'Ramah',
        visualGuidance: null,
        preferredTerms: [],
        forbiddenTerms: ['murah'],
        forbiddenClaims: [],
        allowedCtas: ['DM kami'],
        requiredHashtags: [],
        forbiddenHashtags: [],
        approvedExamples: [],
        rejectedExamples: [],
        captionMinLength: null,
        captionMaxLength: 300,
        hashtagMin: null,
        hashtagMax: 10,
        topicRepeatDays: 14,
        changeNote: 'Aturan awal',
        createdBy: 'Admin',
        createdAt: '2026-11-08T03:00:00Z',
    };

    it('shows the current version read-only with its history', () => {
        const wrapper = mount(BrandRulesPanel, {
            props: { brandId: 'b', rules, versions: [{ version: 3, changeNote: 'Aturan awal', createdBy: 'Admin', createdAt: '2026-11-08T03:00:00Z' }] },
        });

        expect(wrapper.text()).toContain('Versi 3');
        expect(wrapper.get('[data-testid="brand-rules"]').text()).toContain('murah');
        expect(wrapper.text()).toContain('Jarak topik sama: 14 hari');
        expect(wrapper.find('form').exists()).toBe(false);
    });

    it('opens the whole rule set as one headed guide with a required change note', async () => {
        const wrapper = mount(BrandRulesPanel, { props: { brandId: 'b', rules, versions: [] } });

        await wrapper.get('button').trigger('click');

        expect(wrapper.find('form').exists()).toBe(true);
        const guide = (wrapper.get('#rules-guide').element as HTMLTextAreaElement).value;
        expect(guide).toContain('[AUDIENS]\nPemilik UMKM');
        expect(guide).toContain('[KATA TERLARANG]\nmurah');
        expect(wrapper.get('[data-testid="brand-guide-summary"]').text()).toContain('Kata terlarang1');
        expect(wrapper.text()).toContain('Alasan perubahan *');
    });

    it('explains an empty brand without guessing data', () => {
        const wrapper = mount(BrandRulesPanel, { props: { brandId: 'b', rules: null, versions: [] } });

        expect(wrapper.text()).toContain('Jangan menebak harga atau klaim.');
    });
});

describe('brand guide document', () => {
    it('formats existing structured rules without losing their values', () => {
        const guide = formatBrandGuide({
            version: 1,
            audience: 'Pemilik toko',
            voice: 'Hangat',
            visualGuidance: 'Foto terang',
            preferredTerms: ['praktis'],
            forbiddenTerms: ['termurah'],
            forbiddenClaims: ['dijamin laku'],
            allowedCtas: ['Hubungi kami'],
            requiredHashtags: ['#brand'],
            forbiddenHashtags: [],
            approvedExamples: ['Contoh baik'],
            rejectedExamples: ['Contoh buruk'],
            captionMinLength: 20,
            captionMaxLength: 300,
            hashtagMin: 1,
            hashtagMax: 5,
            topicRepeatDays: 14,
            changeNote: 'Awal',
            createdBy: 'Admin',
            createdAt: '2026-11-08T03:00:00Z',
        });
        const preview = previewBrandGuide(guide);

        expect(preview.valid).toBe(true);
        expect(preview).toMatchObject({ forbiddenTerms: 1, allowedCtas: 1, requiredHashtags: 1 });
        expect(guide).toContain('Caption maksimal: 300');
        expect(guide).toContain('[CONTOH DITOLAK]\nContoh buruk');
    });

    it('flags unknown and missing headings before submission', () => {
        const malformed = formatBrandGuide(null).replace('[CONTOH DITOLAK]', '[BAGIAN ASING]');
        const preview = previewBrandGuide(malformed);

        expect(preview.valid).toBe(false);
        expect(preview.errors.join(' ')).toContain('[BAGIAN ASING]');
        expect(preview.errors.join(' ')).toContain('[CONTOH DITOLAK]');
    });
});
