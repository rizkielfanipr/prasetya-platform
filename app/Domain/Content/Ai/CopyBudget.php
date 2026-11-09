<?php

declare(strict_types=1);

namespace App\Domain\Content\Ai;

use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Enums\GenerationStatus;
use App\Domain\Content\Models\ContentGenerationRun;
use Carbon\CarbonImmutable;

/**
 * Whether AI copy may run now, and an optional cost estimate. There is no spending limit in the application (owner
 * decision, 2026-11-08): set a spending limit at the provider instead. Prices are optional; without them cost is 0.
 * Nothing here calls the provider.
 */
final class CopyBudget
{
    /** Provider failures that count toward the circuit breaker; invalid output and budget stops do not. */
    private const array PROVIDER_FAILURES = ['timeout', 'rate_limited', 'server_error', 'unreachable', 'auth'];

    public function __construct(private readonly CopyGenerator $generator, private readonly CopyModels $models) {}

    /** Why AI copy cannot be used at all with this configuration, or null when it can. */
    public function unavailableReason(): ?string
    {
        $reason = $this->generator->unavailableReason();
        if ($reason !== null) {
            return $reason;
        }
        if ($this->generator->provider() === 'fake' && app()->isProduction()) {
            return 'Provider contoh (fake) tidak boleh dipakai di production.';
        }

        return null;
    }

    public function cost(int $inputTokens, int $outputTokens): float
    {
        return round(($inputTokens * $this->priceIn() + $outputTokens * $this->priceOut()) / 1_000_000, 6);
    }

    /** When the provider failed repeatedly just now, the time new requests may resume; otherwise null. */
    public function circuitOpenUntil(): ?CarbonImmutable
    {
        $threshold = (int) config('content.ai.circuit.failures');
        $minutes = (int) config('content.ai.circuit.minutes');
        $recent = ContentGenerationRun::query()
            ->whereIn('status', [GenerationStatus::Succeeded, GenerationStatus::Failed])
            ->where('finished_at', '>=', now()->subMinutes($minutes))
            ->latest('finished_at')->latest('id')
            ->limit($threshold)
            ->get(['status', 'error_code', 'finished_at']);

        $allFailed = $recent->count() === $threshold
            && $recent->every(static fn (ContentGenerationRun $run): bool => $run->status === GenerationStatus::Failed && in_array($run->error_code, self::PROVIDER_FAILURES, true));
        $last = $recent->first()?->finished_at;

        return $allFailed && $last !== null ? CarbonImmutable::instance($last)->addMinutes($minutes) : null;
    }

    /** The fake provider is free; a real one is priced by the model in use. */
    private function priceIn(): float
    {
        return $this->generator->provider() === 'fake' ? 0.0 : ($this->models->selected()->priceInputPerMillion ?? 0.0);
    }

    private function priceOut(): float
    {
        return $this->generator->provider() === 'fake' ? 0.0 : ($this->models->selected()->priceOutputPerMillion ?? 0.0);
    }
}
