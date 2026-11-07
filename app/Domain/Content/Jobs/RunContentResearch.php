<?php

declare(strict_types=1);

namespace App\Domain\Content\Jobs;

use App\Domain\Content\Actions\RunResearch;
use App\Domain\Content\Enums\ResearchStatus;
use App\Domain\Content\Models\ContentResearchRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/** Runs one web search on the content-ai queue. */
final class RunContentResearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public readonly int $runId)
    {
        $this->timeout = (int) config('content.ai.search.timeout_seconds') + 30;
    }

    public function handle(RunResearch $run): void
    {
        $run($this->runId);
    }

    public function failed(?Throwable $exception): void
    {
        ContentResearchRun::query()->whereKey($this->runId)->whereIn('status', [ResearchStatus::Queued, ResearchStatus::Running])->update([
            'status' => ResearchStatus::Failed,
            'error_code' => 'crashed',
            'error_message' => 'Riset berhenti tanpa hasil. Coba lagi.',
            'finished_at' => now(),
        ]);
    }
}
