<?php

declare(strict_types=1);

namespace App\Domain\Content\Ai;

use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Enums\CopyField;
use App\Domain\Content\Enums\RuleSeverity;
use App\Domain\Content\Models\ContentBrandProfile;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Support\ContentRuleValidator;
use App\Domain\Content\Support\RuleFinding;

/**
 * Runs the configured provider over versioned scenarios and checks every answer with the deterministic brand rules.
 * Required before enabling or switching a provider (ADR-0011). Writes nothing to the database.
 */
final class CopyEvaluation
{
    public function __construct(
        private readonly CopyGenerator $generator,
        private readonly CopyPromptBuilder $prompts,
        private readonly ContentRuleValidator $rules,
        private readonly CopyBudget $budget,
    ) {}

    /**
     * @param  array<string, mixed>  $scenario
     * @return array{name: string, ok: bool, errors: list<string>, warnings: list<string>, output: array<string, string>|null, tokens: int, cost: float, latency_ms: int}
     */
    public function run(array $scenario): array
    {
        $fields = CopyField::cases();
        $profile = new ContentBrandProfile;
        $rules = is_array($scenario['rules'] ?? null) ? $scenario['rules'] : [];
        foreach ([...ContentBrandProfile::TEXTS, ...ContentBrandProfile::NUMBERS] as $key) {
            $profile->setAttribute($key, $rules[$key] ?? null);
        }
        foreach (ContentBrandProfile::LISTS as $key) {
            $profile->setAttribute($key, array_values((array) ($rules[$key] ?? [])));
        }
        $base = new ContentRevision(array_intersect_key((array) ($scenario['revision'] ?? []), array_flip(ContentRevision::FIELDS)));
        /** @var list<string> $facts */
        $facts = array_values((array) ($scenario['facts'] ?? []));
        /** @var array<string, string|null> $plan */
        $plan = (array) ($scenario['plan'] ?? []);
        $prompt = $this->prompts->compose((string) ($scenario['brand'] ?? 'Brand'), $profile, $facts, $plan, $base, $fields, (int) config('content.ai.copy.max_output_tokens'));

        $result = ['name' => (string) ($scenario['name'] ?? 'tanpa-nama'), 'ok' => false, 'errors' => [], 'warnings' => [], 'output' => null, 'tokens' => 0, 'cost' => 0.0, 'latency_ms' => 0];
        $started = hrtime(true);
        try {
            $response = $this->generator->generate($prompt);
            $result['tokens'] = $response->inputTokens + $response->outputTokens;
            $result['cost'] = $this->budget->cost($response->inputTokens, $response->outputTokens);
            $copy = GeneratedCopy::parse($response->content, $fields);
        } catch (CopyProviderFailed $failure) {
            $result['errors'][] = "{$failure->reason}: {$failure->getMessage()}";
            $result['latency_ms'] = (int) ((hrtime(true) - $started) / 1_000_000);

            return $result;
        }
        $result['latency_ms'] = (int) ((hrtime(true) - $started) / 1_000_000);
        $result['output'] = $copy->values;

        $findings = $this->rules->evaluate($profile, $facts, new ContentRevision($copy->values));
        $result['errors'] = array_values(array_map(static fn (RuleFinding $f): string => $f->message, array_filter($findings, static fn (RuleFinding $f): bool => $f->severity === RuleSeverity::Error)));
        $result['warnings'] = array_values(array_map(static fn (RuleFinding $f): string => $f->message, array_filter($findings, static fn (RuleFinding $f): bool => $f->severity === RuleSeverity::Warning)));
        $result['ok'] = $result['errors'] === [];

        return $result;
    }
}
