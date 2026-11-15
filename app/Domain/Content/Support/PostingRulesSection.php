<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Exceptions\InvalidBrandGuide;
use App\Domain\Content\Models\ContentBrandProfile;

/**
 * The brand's posting rules written inside Brand Knowledge (ADR-0017): one `## Aturan Posting` section of bracketed
 * blocks, the same blocks and [BATAS] labels as the retired Brand Guide editor. A missing block counts as empty; a missing
 * [BATAS] label as unset. Prose before the first block is allowed and ignored by the parser (the AI still reads it).
 */
final class PostingRulesSection
{
    public const string HEADING = 'Aturan Posting';

    /** @var array<string, string> block => stored list field */
    public const array LISTS = [
        'KATA TERLARANG' => 'forbidden_terms',
        'KLAIM TERLARANG' => 'forbidden_claims',
        'CTA DISETUJUI' => 'allowed_ctas',
        'HASHTAG WAJIB' => 'required_hashtags',
        'HASHTAG TERLARANG' => 'forbidden_hashtags',
    ];

    /** @var array<string, array{string, int, int}> [BATAS] label => [stored field, min, max] */
    public const array LIMIT_LABELS = [
        'Caption minimal' => ['caption_min_length', 0, 2200],
        'Caption maksimal' => ['caption_max_length', 1, 2200],
        'Hashtag minimal' => ['hashtag_min', 0, 30],
        'Hashtag maksimal' => ['hashtag_max', 0, 30],
        'Jarak topik (hari)' => ['topic_repeat_days', 1, 365],
    ];

    private const string LIMITS = 'BATAS';

    /** The section's body, without its heading, or null when the document has no such section. */
    public function extract(string $source): ?string
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $source));
        $start = null;
        foreach ($lines as $index => $line) {
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/u', $line, $match) !== 1) {
                continue;
            }
            $level = strlen($match[1]);
            if ($start === null) {
                if (mb_strtolower(trim($match[2])) === mb_strtolower(self::HEADING)) {
                    $start = ['index' => $index, 'level' => $level];
                }

                continue;
            }
            // The section ends at the next heading of the same or a higher level.
            if ($level <= $start['level']) {
                return implode("\n", array_slice($lines, $start['index'] + 1, $index - $start['index'] - 1));
            }
        }

        return $start === null ? null : implode("\n", array_slice($lines, $start['index'] + 1));
    }

    /**
     * Rule values for ContentBrandProfile::LISTS (the five above) and NUMBERS.
     *
     * @return array<string, mixed>
     */
    public function parse(string $body): array
    {
        $blocks = [];
        $current = null;
        $allowed = [self::LIMITS, ...array_keys(self::LISTS)];
        foreach (explode("\n", $body) as $line) {
            if (preg_match('/^\s*\[([^]]+)]\s*$/u', $line, $match) === 1) {
                $block = mb_strtoupper(trim($match[1]));
                if (! in_array($block, $allowed, true)) {
                    throw new InvalidBrandGuide("Aturan Posting: bagian [{$block}] tidak dikenali. Gunakan ".implode(', ', array_map(static fn (string $b): string => "[{$b}]", $allowed)).'.');
                }
                if (array_key_exists($block, $blocks)) {
                    throw new InvalidBrandGuide("Aturan Posting: bagian [{$block}] ditulis lebih dari sekali.");
                }
                $current = $block;
                $blocks[$current] = [];

                continue;
            }
            if ($current !== null) {
                $blocks[$current][] = $line;
            }
        }

        $values = [];
        foreach (self::LISTS as $block => $field) {
            $entries = array_map(static fn (string $line): string => trim((string) preg_replace('/^[-*•]\s*/u', '', trim($line))), $blocks[$block] ?? []);
            $entries = array_values(array_unique(array_filter($entries, static fn (string $entry): bool => $entry !== '')));
            if (count($entries) > 50) {
                throw new InvalidBrandGuide("Aturan Posting: bagian [{$block}] maksimal 50 baris.");
            }
            foreach ($entries as $entry) {
                if (mb_strlen($entry) > 200) {
                    throw new InvalidBrandGuide("Aturan Posting: setiap baris [{$block}] maksimal 200 karakter.");
                }
            }
            $values[$field] = $entries;
        }

        return [...$values, ...$this->limits($blocks[self::LIMITS] ?? [])];
    }

    /** The current rules written as the section, for moving them into the document. */
    public function render(?ContentBrandProfile $profile): string
    {
        $lines = ['## '.self::HEADING, '', '['.self::LIMITS.']'];
        foreach (self::LIMIT_LABELS as $label => [$field]) {
            $value = $profile?->{$field};
            $lines[] = $value === null ? "{$label}:" : "{$label}: {$value}";
        }
        foreach (self::LISTS as $block => $field) {
            $lines[] = '';
            $lines[] = "[{$block}]";
            foreach ($profile === null ? [] : $profile->{$field} as $entry) {
                $lines[] = $entry;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, int|null>
     */
    private function limits(array $lines): array
    {
        $values = array_fill_keys(array_column(self::LIMIT_LABELS, 0), null);
        $seen = [];
        foreach (array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== '') as $line) {
            if (preg_match('/^([^:]+):\s*(\d*)\s*$/u', $line, $match) !== 1 || ! isset(self::LIMIT_LABELS[trim($match[1])])) {
                throw new InvalidBrandGuide('Aturan Posting: format [BATAS] tidak valid. Tulis misalnya “Caption maksimal: 700”, dengan label '.implode(', ', array_map(static fn (string $l): string => "“{$l}”", array_keys(self::LIMIT_LABELS))).'.');
            }
            $label = trim($match[1]);
            if (isset($seen[$label])) {
                throw new InvalidBrandGuide("Aturan Posting: batas “{$label}” ditulis lebih dari sekali.");
            }
            $seen[$label] = true;
            [$field, $min, $max] = self::LIMIT_LABELS[$label];
            if ($match[2] === '') {
                continue;
            }
            $number = (int) $match[2];
            if ($number < $min || $number > $max) {
                throw new InvalidBrandGuide("Aturan Posting: batas “{$label}” harus antara {$min} dan {$max}.");
            }
            $values[$field] = $number;
        }

        return $values;
    }
}
