<?php

declare(strict_types=1);

namespace App\Integrations\ContentAi;

use App\Domain\Content\Ai\CopyPrompt;
use App\Domain\Content\Ai\CopyResponse;
use App\Domain\Content\Contracts\CopyGenerator;

/**
 * Deterministic drafts for local demos, browser tests, and development without a key. It reads the working title and
 * the first approved CTA from the prompt's DATA and states no number. Refused in production by CopyBudget.
 */
final class FakeCopyGenerator implements CopyGenerator
{
    public function provider(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-copy-1';
    }

    public function unavailableReason(): ?string
    {
        return null;
    }

    public function generate(CopyPrompt $prompt): CopyResponse
    {
        $data = json_decode((string) substr($prompt->user, (int) strpos($prompt->user, "DATA:\n") + 6), true);
        if ($prompt->version === 'brand-knowledge-v2') {
            $answers = is_array($data) && is_array($data['jawaban_wawancara'] ?? null) ? $data['jawaban_wawancara'] : [];
            $current = is_array($data) && is_string($data['dokumen_saat_ini'] ?? null) ? trim($data['dokumen_saat_ini']) : '';
            $sections = $current === '' ? [] : [$current];
            foreach ($answers as $label => $answer) {
                if (is_string($answer) && trim($answer) !== '') {
                    $sections[] = mb_convert_case(str_replace('_', ' ', (string) $label), MB_CASE_TITLE, 'UTF-8')."\n".trim($answer);
                }
            }
            $content = (string) json_encode(['source_text' => implode("\n\n", $sections)], JSON_UNESCAPED_UNICODE);

            return new CopyResponse($content, $prompt->estimatedInputTokens(), (int) ceil(mb_strlen($content) / 3), 'fake-'.substr(md5($prompt->user), 0, 12));
        }
        $title = is_array($data) ? (string) ($data['rencana']['judul_kerja'] ?? 'Konten brand') : 'Konten brand';
        $cta = is_array($data) ? ($data['aturan_brand']['cta_disetujui'][0] ?? null) : null;
        $tags = is_array($data) ? ($data['aturan_brand']['hashtag_wajib'] ?? []) : [];
        preg_match('/kunci persis: (\{.*\})/u', $prompt->user, $keys);
        $wanted = array_keys((array) json_decode($keys[1] ?? '{}', true));

        $all = [
            'caption' => '[Draft AI contoh] '.rtrim($title, '.!?').(preg_match('/[?!]$/u', $title) === 1 ? mb_substr($title, -1) : '.')."\n\nWebsite yang rapi membantu calon pelanggan mengenal usaha Anda.".(is_string($cta) ? "\n\n{$cta}." : ''),
            'hashtags' => trim(implode(' ', array_map(static fn (string $t): string => '#'.ltrim($t, '#'), $tags)).' #websiteumkm #bisnisonline'),
            'alt_text' => "Kartu berwarna brand bertuliskan \"{$title}\".",
            'visual_direction' => "Latar polos warna brand, judul besar \"{$title}\", satu ikon pendukung, logo kecil di pojok kanan bawah.",
        ];
        $content = (string) json_encode(array_intersect_key($all, array_flip($wanted)), JSON_UNESCAPED_UNICODE);

        return new CopyResponse($content, $prompt->estimatedInputTokens(), (int) ceil(mb_strlen($content) / 3), 'fake-'.substr(md5($prompt->user), 0, 12));
    }
}
