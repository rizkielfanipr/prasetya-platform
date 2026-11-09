<?php

declare(strict_types=1);

namespace App\Domain\Content\Jobs;

use App\Domain\Content\Actions\RunCopyGeneration;
use App\Domain\Content\Enums\GenerationStatus;
use App\Domain\Content\Models\ContentGenerationRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/** Runs one AI copy generation on the content-ai queue. Retries happen inside the run, bounded by configuration. */
final class GenerateContentCopy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public readonly int $runId)
    {
        $this->timeout = (int) config('content.ai.copy.timeout_seconds') * max(1, (int) config('content.ai.copy.max_attempts')) + 30;
    }

    public function handle(RunCopyGeneration $run): void
    {
        $run($this->runId);
    }

    /** An unexpected crash still closes the run, so the item is not blocked until it goes stale. */
    public function failed(?Throwable $exception): void
    {
        ContentGenerationRun::query()->whereKey($this->runId)->whereIn('status', [GenerationStatus::Queued, GenerationStatus::Running])->update([
            'status' => GenerationStatus::Failed,
            'error_code' => 'crashed',
            'error_message' => 'Proses AI berhenti tanpa hasil. Coba lagi.',
            'finished_at' => now(),
        ]);
    }
}
