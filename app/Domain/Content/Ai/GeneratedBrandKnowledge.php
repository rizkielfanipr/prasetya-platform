<?php

declare(strict_types=1);

namespace App\Domain\Content\Ai;

use JsonException;

final readonly class GeneratedBrandKnowledge
{
    private function __construct(public string $sourceText) {}

    /** @throws CopyProviderFailed */
    public static function parse(string $response, int $inputTokens = 0, int $outputTokens = 0): self
    {
        foreach (self::jsonCandidates($response) as $candidate) {
            try {
                $data = json_decode($candidate, true, flags: JSON_THROW_ON_ERROR);
                if (is_string($data)) {
                    $data = json_decode($data, true, flags: JSON_THROW_ON_ERROR);
                }
            } catch (JsonException) {
                continue;
            }
            $text = is_array($data) ? ($data['source_text'] ?? null) : null;
            if (is_string($text) && trim($text) !== '') {
                return new self(self::validText($text, $inputTokens, $outputTokens));
            }
        }

        $markdown = self::markdownDocument($response);
        if ($markdown !== null) {
            return new self(self::validText($markdown, $inputTokens, $outputTokens));
        }

        throw CopyProviderFailed::invalidOutput('Jawaban AI tidak berisi JSON atau dokumen Brand Knowledge yang dapat dibaca.', $inputTokens, $outputTokens);
    }

    /** @return list<string> */
    private static function jsonCandidates(string $response): array
    {
        $response = trim($response);
        $candidates = [$response];
        if (preg_match('/```(?:json)?\s*(.*?)\s*```/isu', $response, $fence) === 1) {
            $candidates[] = trim($fence[1]);
        }
        $first = mb_strpos($response, '{');
        $last = mb_strrpos($response, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $candidates[] = mb_substr($response, $first, $last - $first + 1);
        }

        return array_values(array_unique(array_filter($candidates, static fn (string $candidate): bool => $candidate !== '')));
    }

    private static function markdownDocument(string $response): ?string
    {
        $response = trim($response);
        if (preg_match('/```(?:markdown|md)?\s*(.*?)\s*```/isu', $response, $fence) === 1) {
            $response = trim($fence[1]);
        }
        if (preg_match('/^#{1,3}\s+.+$/mu', $response, $first, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }
        $document = trim(substr($response, (int) $first[0][1]));
        if (preg_match_all('/^#{1,3}\s+.+$/mu', $document) < 2) {
            return null;
        }

        return $document;
    }

    /** @throws CopyProviderFailed */
    private static function validText(string $text, int $inputTokens, int $outputTokens): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
        if (mb_strlen($text) > 50_000) {
            throw CopyProviderFailed::invalidOutput('Jawaban AI melebihi 50.000 karakter.', $inputTokens, $outputTokens);
        }

        return $text;
    }
}
