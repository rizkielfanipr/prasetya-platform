import type { BrandRules } from '@/types/content';

const headings = [
    'AUDIENS',
    'GAYA BAHASA',
    'ARAHAN VISUAL',
    'BATAS',
    'KATA TERLARANG',
    'KLAIM TERLARANG',
    'CTA DISETUJUI',
    'HASHTAG WAJIB',
    'HASHTAG TERLARANG',
    'KATA DIANJURKAN',
    'CONTOH DISETUJUI',
    'CONTOH DITOLAK',
] as const;

const listHeadings = headings.slice(4);
const limitLabels = ['Caption minimal', 'Caption maksimal', 'Hashtag minimal', 'Hashtag maksimal', 'Jarak topik (hari)'] as const;
const limitBounds: Record<(typeof limitLabels)[number], readonly [number, number]> = {
    'Caption minimal': [0, 2200],
    'Caption maksimal': [1, 2200],
    'Hashtag minimal': [0, 30],
    'Hashtag maksimal': [0, 30],
    'Jarak topik (hari)': [1, 365],
};

function value(value: string | number | null): string {
    return value === null ? '' : String(value);
}

function lines(values: string[]): string {
    return values.join('\n');
}

/** Canonical document shown by the single Brand Guide editor. */
export function formatBrandGuide(rules: BrandRules | null): string {
    return `[AUDIENS]
${rules?.audience ?? ''}

[GAYA BAHASA]
${rules?.voice ?? ''}

[ARAHAN VISUAL]
${rules?.visualGuidance ?? ''}

[BATAS]
Caption minimal: ${value(rules?.captionMinLength ?? null)}
Caption maksimal: ${value(rules?.captionMaxLength ?? null)}
Hashtag minimal: ${value(rules?.hashtagMin ?? null)}
Hashtag maksimal: ${value(rules?.hashtagMax ?? null)}
Jarak topik (hari): ${value(rules?.topicRepeatDays ?? null)}

[KATA TERLARANG]
${lines(rules?.forbiddenTerms ?? [])}

[KLAIM TERLARANG]
${lines(rules?.forbiddenClaims ?? [])}

[CTA DISETUJUI]
${lines(rules?.allowedCtas ?? [])}

[HASHTAG WAJIB]
${lines(rules?.requiredHashtags ?? [])}

[HASHTAG TERLARANG]
${lines(rules?.forbiddenHashtags ?? [])}

[KATA DIANJURKAN]
${lines(rules?.preferredTerms ?? [])}

[CONTOH DISETUJUI]
${lines(rules?.approvedExamples ?? [])}

[CONTOH DITOLAK]
${lines(rules?.rejectedExamples ?? [])}`;
}

export interface BrandGuidePreview {
    valid: boolean;
    errors: string[];
    filledBasics: number;
    forbiddenTerms: number;
    forbiddenClaims: number;
    allowedCtas: number;
    requiredHashtags: number;
    forbiddenHashtags: number;
}

/** Lightweight client preview. The PHP parser remains authoritative on save. */
export function previewBrandGuide(guide: string): BrandGuidePreview {
    const sections = new Map<string, string[]>();
    const errors: string[] = [];
    let current: string | null = null;
    for (const line of guide.replaceAll('\r\n', '\n').split('\n')) {
        const match = line.match(/^\s*\[([^\]]+)\]\s*$/u);
        if (match) {
            const [, rawHeading = ''] = match;
            const heading = rawHeading.trim().toUpperCase();
            if (!(headings as readonly string[]).includes(heading)) errors.push(`Bagian [${heading}] tidak dikenali.`);
            else if (sections.has(heading)) errors.push(`Bagian [${heading}] ditulis lebih dari sekali.`);
            current = heading;
            if (!sections.has(heading)) sections.set(heading, []);
        } else if (current !== null && sections.has(current)) sections.get(current)?.push(line);
        else if (line.trim() !== '') errors.push('Tulis isi di bawah heading seperti [AUDIENS].');
    }
    const missing = headings.filter((heading) => !sections.has(heading));
    if (missing.length) errors.push(`Bagian wajib belum ada: ${missing.map((heading) => `[${heading}]`).join(', ')}.`);

    const limits = sections.get('BATAS')?.filter((line) => line.trim() !== '') ?? [];
    const foundLabels = new Set<string>();
    for (const line of limits) {
        const match = line.trim().match(/^([^:]+):\s*(\d*)$/u);
        const label = match?.[1]?.trim() ?? '';
        if (!match || !(limitLabels as readonly string[]).includes(label)) {
            errors.push(`Format batas tidak valid: “${line.trim()}”.`);
            continue;
        }
        if (foundLabels.has(label)) errors.push(`Batas “${label}” ditulis lebih dari sekali.`);
        foundLabels.add(label);
        const rawNumber = match[2] ?? '';
        const [minimum, maximum] = limitBounds[label as (typeof limitLabels)[number]];
        if (rawNumber !== '' && (Number(rawNumber) < minimum || Number(rawNumber) > maximum)) {
            errors.push(`Batas “${label}” harus antara ${minimum} dan ${maximum}.`);
        }
    }
    const missingLimits = limitLabels.filter((label) => !foundLabels.has(label));
    if (missingLimits.length) errors.push(`Label batas belum ada: ${missingLimits.join(', ')}.`);

    const count = (heading: string): number => (sections.get(heading) ?? []).filter((line) => line.trim() !== '').length;
    for (const heading of listHeadings) {
        if (count(heading) > 50) errors.push(`[${heading}] maksimal 50 baris.`);
    }

    return {
        valid: errors.length === 0,
        errors: [...new Set(errors)],
        filledBasics: ['AUDIENS', 'GAYA BAHASA', 'ARAHAN VISUAL'].filter((heading) => count(heading) > 0).length,
        forbiddenTerms: count('KATA TERLARANG'),
        forbiddenClaims: count('KLAIM TERLARANG'),
        allowedCtas: count('CTA DISETUJUI'),
        requiredHashtags: count('HASHTAG WAJIB'),
        forbiddenHashtags: count('HASHTAG TERLARANG'),
    };
}
