<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Exceptions\InvalidBrandGuide;

/** Turns the single, headed Brand Guide editor into the existing deterministic rule structure. */
final class BrandGuideParser
{
    /** @var array<string, string> heading => stored field */
    public const array TEXT_SECTIONS = [
        'AUDIENS' => 'audience',
        'GAYA BAHASA' => 'voice',
        'ARAHAN VISUAL' => 'visual_guidance',
    ];

    /** @var array<string, string> heading => stored field */
    public const array LIST_SECTIONS = [
        'KATA TERLARANG' => 'forbidden_terms',
        'KLAIM TERLARANG' => 'forbidden_claims',
        'CTA DISETUJUI' => 'allowed_ctas',
        'HASHTAG WAJIB' => 'required_hashtags',
        'HASHTAG TERLARANG' => 'forbidden_hashtags',
        'KATA DIANJURKAN' => 'preferred_terms',
        'CONTOH DISETUJUI' => 'approved_examples',
        'CONTOH DITOLAK' => 'rejected_examples',
    ];

    /** @var array<string, array{string, int, int}> label => [stored field, min, max] */
    public const array LIMITS = [
        'Caption minimal' => ['caption_min_length', 0, 2200],
        'Caption maksimal' => ['caption_max_length', 1, 2200],
        'Hashtag minimal' => ['hashtag_min', 0, 30],
        'Hashtag maksimal' => ['hashtag_max', 0, 30],
        'Jarak topik (hari)' => ['topic_repeat_days', 1, 365],
    ];

    private const string LIMITS_HEADING = 'BATAS';

    /** @return array<string, mixed> */
    public function parse(string $guide): array
    {
        $sections = $this->sections(str_replace("\r\n", "\n", trim($guide)));
        $values = [];
        foreach (self::TEXT_SECTIONS as $heading => $field) {
            $text = trim(implode("\n", $sections[$heading]));
            if (mb_strlen($text) > 2000) {
                throw new InvalidBrandGuide("Bagian [{$heading}] maksimal 2.000 karakter.");
            }
            $values[$field] = $text === '' ? null : $text;
        }
        foreach (self::LIST_SECTIONS as $heading => $field) {
            $entries = array_values(array_unique(array_filter(array_map('trim', $sections[$heading]), static fn (string $line): bool => $line !== '')));
            if (count($entries) > 50) {
                throw new InvalidBrandGuide("Bagian [{$heading}] maksimal 50 baris.");
            }
            $max = in_array($field, ['approved_examples', 'rejected_examples'], true) ? 2200 : 200;
            foreach ($entries as $entry) {
                if (mb_strlen($entry) > $max) {
                    throw new InvalidBrandGuide("Setiap baris [{$heading}] maksimal {$max} karakter.");
                }
            }
            $values[$field] = $entries;
        }

        return [...$values, ...$this->limits($sections[self::LIMITS_HEADING])];
    }

    /** @return array<string, list<string>> */
    private function sections(string $guide): array
    {
        $allowed = [...array_keys(self::TEXT_SECTIONS), self::LIMITS_HEADING, ...array_keys(self::LIST_SECTIONS)];
        $sections = [];
        $current = null;
        foreach (explode("\n", $guide) as $line) {
            if (preg_match('/^\s*\[([^]]+)]\s*$/u', $line, $match) === 1) {
                $heading = mb_strtoupper(trim($match[1]));
                if (! in_array($heading, $allowed, true)) {
                    throw new InvalidBrandGuide("Bagian [{$heading}] tidak dikenali.");
                }
                if (array_key_exists($heading, $sections)) {
                    throw new InvalidBrandGuide("Bagian [{$heading}] ditulis lebih dari sekali.");
                }
                $current = $heading;
                $sections[$current] = [];

                continue;
            }
            if ($current === null) {
                if (trim($line) !== '') {
                    throw new InvalidBrandGuide('Tulis isi di bawah heading seperti [AUDIENS].');
                }

                continue;
            }
            $sections[$current][] = $line;
        }
        $missing = array_values(array_diff($allowed, array_keys($sections)));
        if ($missing !== []) {
            throw new InvalidBrandGuide('Bagian wajib belum ada: '.implode(', ', array_map(static fn (string $heading): string => "[{$heading}]", $missing)).'.');
        }

        return $sections;
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, int|null>
     */
    private function limits(array $lines): array
    {
        $values = array_fill_keys(array_column(self::LIMITS, 0), null);
        $seen = [];
        foreach (array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== '') as $line) {
            if (preg_match('/^([^:]+):\s*(\d*)\s*$/u', $line, $match) !== 1 || ! isset(self::LIMITS[trim($match[1])])) {
                throw new InvalidBrandGuide('Format [BATAS] tidak valid. Gunakan label dari template, misalnya “Caption maksimal: 2200”.');
            }
            $label = trim($match[1]);
            if (isset($seen[$label])) {
                throw new InvalidBrandGuide("Batas “{$label}” ditulis lebih dari sekali.");
            }
            $seen[$label] = true;
            [$field, $min, $max] = self::LIMITS[$label];
            if ($match[2] === '') {
                $values[$field] = null;

                continue;
            }
            $number = (int) $match[2];
            if ($number < $min || $number > $max) {
                throw new InvalidBrandGuide("Batas “{$label}” harus antara {$min} dan {$max}.");
            }
            $values[$field] = $number;
        }
        $missing = array_values(array_diff(array_keys(self::LIMITS), array_keys($seen)));
        if ($missing !== []) {
            throw new InvalidBrandGuide('Label [BATAS] belum ada: '.implode(', ', $missing).'. Biarkan nilainya kosong jika tidak digunakan.');
        }

        return $values;
    }
}
