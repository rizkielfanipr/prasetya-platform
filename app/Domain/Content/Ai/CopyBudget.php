<?php

declare(strict_types=1);

namespace App\Domain\Content\Ai;

use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Enums\GenerationStatus;
use App\Domain\Content\Models\ContentGenerationRun;
use App\Support\BusinessTime;
use Carbon\CarbonImmutable;

/**
 * Whether AI copy may run now, and what it costs. Cost is estimated from configured per-million-token prices, so the
 * weekly and per-run limits hold for any provider. Nothing here calls the provider.
 */
final class CopyBudget
{
    /** Provider failures that count toward the circuit breaker; invalid output and budget stops do not. */
    private const array PROVIDER_FAILURES = ['timeout', 'rate_limited', 'server_error', 'unreachable', 'auth'];

    public function __construct(private readonly CopyGenerator $generator) {}

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
        if ($this->generator->provider() !== 'fake' && ($this->priceIn() <= 0.0 || $this->priceOut() <= 0.0)) {
            return 'Harga per token provider AI belum diisi, jadi budget tidak dapat dijaga.';
        }

        return null;
    }

    public function cost(int $inputTokens, int $outputTokens): float
    {
        return round(($inputTokens * $this->priceIn() + $outputTokens * $this->priceOut()) / 1_000_000, 6);
    }

    /** The most a prompt can cost: its estimated input plus the full output allowance. */
    public function maxCost(CopyPrompt $prompt): float
    {
        return $this->cost($prompt->estimatedInputTokens(), $prompt->maxOutputTokens);
    }

    public function runLimit(): float
    {
        return (float) config('content.ai.budget.run_usd');
    }

    public function weekLimit(): float
    {
        return (float) config('content.ai.budget.week_usd');
    }

    /** Spent this business week (Monday 00:00 WIB onwards), by every run that reached the provider. */
    public function spentThisWeek(): float
    {
        $start = CarbonImmutable::now(BusinessTime::zone())->startOfWeek(CarbonImmutable::MONDAY)->startOfDay()->utc();

        return round((float) ContentGenerationRun::query()->where('started_at', '>=', $start)->sum('cost_usd'), 6);
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

    private function priceIn(): float
    {
        return (float) config('content.ai.copy.price_input_per_million');
    }

    private function priceOut(): float
    {
        return (float) config('content.ai.copy.price_output_per_million');
    }
}
