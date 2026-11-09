<?php

declare(strict_types=1);

use App\Domain\Content\Ai\CopyProviderFailed;
use App\Domain\Content\Ai\GeneratedBrandKnowledge;

it('reads strict, fenced, and prose-wrapped JSON responses', function (string $response): void {
    expect(GeneratedBrandKnowledge::parse($response)->sourceText)->toBe("# Brand\n\n## Audiens\nUMKM");
})->with([
    'strict JSON' => '{"source_text":"# Brand\\n\\n## Audiens\\nUMKM"}',
    'JSON fence' => "```json\n{\"source_text\":\"# Brand\\n\\n## Audiens\\nUMKM\"}\n```",
    'intro text' => "Berikut hasilnya:\n{\"source_text\":\"# Brand\\n\\n## Audiens\\nUMKM\"}\nSemoga membantu.",
]);

it('accepts a structured Markdown document when a model ignores the JSON response format', function (): void {
    $response = "Berikut Brand Knowledge Anda:\n\n# Prasetya Webworks\n\n## Ringkasan dan Positioning\nMembuat website.\n\n## Target Audiens\nPemilik UMKM.";

    expect(GeneratedBrandKnowledge::parse($response)->sourceText)->toStartWith('# Prasetya Webworks')
        ->not->toContain('Berikut Brand Knowledge Anda');
});

it('rejects arbitrary prose and keeps provider usage on the failure', function (): void {
    try {
        GeneratedBrandKnowledge::parse('Maaf, saya tidak dapat membantu.', 120, 30);
        test()->fail('The arbitrary response should be refused.');
    } catch (CopyProviderFailed $failure) {
        expect($failure->reason)->toBe('invalid_output')
            ->and($failure->inputTokens)->toBe(120)
            ->and($failure->outputTokens)->toBe(30);
    }
});
