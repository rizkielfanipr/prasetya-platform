<?php

declare(strict_types=1);

namespace App\Domain\Content\Ai;

use App\Domain\Content\Enums\CopyField;

/**
 * A provider's answer, validated like untrusted request data: one JSON object with exactly the requested fields as
 * non-empty strings within limits. Control characters are removed. Anything else is invalid output.
 */
final readonly class GeneratedCopy
{
    /** @param  array<string, string>  $values  revision field => text */
    private function __construct(public array $values) {}

    /**
     * @param  list<CopyField>  $fields
     *
     * @throws CopyProviderFailed
     */
    public static function parse(string $content, array $fields, int $inputTokens = 0, int $outputTokens = 0): self
    {
        $fail = static fn (string $why): CopyProviderFailed => CopyProviderFailed::invalidOutput($why, $inputTokens, $outputTokens);
        // Some models wrap JSON in a code fence even in JSON mode.
        $json = preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/u', '', trim($content)) ?? '';
        $decoded = json_decode($json, true);
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw $fail('Jawaban AI bukan objek JSON.');
        }

        $values = [];
        foreach ($fields as $field) {
            $value = $decoded[$field->value] ?? null;
            if (is_array($value) && $field === CopyField::Hashtags) {
                $value = implode(' ', array_filter($value, 'is_string'));
            }
            if (! is_string($value)) {
                throw $fail("Jawaban AI tidak berisi {$field->label()}.");
            }
            $value = trim((string) preg_replace('/[^\P{C}\n\t]/u', '', str_replace("\r\n", "\n", $value)));
            if ($value === '') {
                throw $fail("Jawaban AI berisi {$field->label()} kosong.");
            }
            if (mb_strlen($value) > $field->maxLength()) {
                throw $fail("Jawaban AI berisi {$field->label()} lebih dari {$field->maxLength()} karakter.");
            }
            $values[$field->value] = $value;
        }

        return new self($values);
    }
}
