<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Content\Ai\CopyBudget;
use App\Domain\Content\Ai\CopyPromptBuilder;
use App\Domain\Content\Ai\CopyProviderFailed;
use App\Domain\Content\Ai\GeneratedCopy;
use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\CopyField;
use App\Domain\Content\Enums\GenerationStatus;
use App\Domain\Content\Models\ContentGenerationRun;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Support\RevisionCopier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;

/**
 * Runs one queued generation. The provider is called outside any database transaction. Retries are bounded and only
 * for transient failures or invalid output. Accepted copy becomes a new draft revision, unless the item changed while
 * the provider worked, in which case nothing is applied and the run is DISCARDED.
 */
final class RunCopyGeneration
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly CopyGenerator $generator,
        private readonly CopyBudget $budget,
        private readonly CopyPromptBuilder $prompts,
        private readonly RevisionCopier $copier,
    ) {}

    public function __invoke(int $runId): void
    {
        $run = DB::transaction(function () use ($runId): ?ContentGenerationRun {
            $run = ContentGenerationRun::query()->lockForUpdate()->find($runId);
            if ($run === null || $run->status !== GenerationStatus::Queued) {
                return null; // Already handled: a redelivered job does nothing.
            }
            $run->forceFill(['status' => GenerationStatus::Running, 'started_at' => now()])->save();

            return $run;
        });
        if ($run === null) {
            return;
        }

        $fields = array_map(static fn (string $f): CopyField => CopyField::from($f), $run->fields);
        $item = $run->item;
        $prompt = $this->prompts->build($item, $run->baseRevision, $fields, (int) config('content.ai.copy.max_output_tokens'), [
            'instruction' => $run->instruction,
        ]);

        $attempts = max(1, (int) config('content.ai.copy.max_attempts'));
        $input = 0;
        $output = 0;
        $latency = 0;
        $requestId = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $started = hrtime(true);
            try {
                $response = $this->generator->generate($prompt);
                $latency += (int) ((hrtime(true) - $started) / 1_000_000);
                $input += $response->inputTokens;
                $output += $response->outputTokens;
                $requestId = $response->requestId;
                $copy = GeneratedCopy::parse($response->content, $fields);
                $this->record($run, $attempt, $input, $output, $latency, $requestId);
                $this->apply($run, $item, $copy);

                return;
            } catch (CopyProviderFailed $failure) {
                $latency += (int) ((hrtime(true) - $started) / 1_000_000);
                $input += $failure->inputTokens;
                $output += $failure->outputTokens;
                if (! $failure->transient || $attempt === $attempts) {
                    $this->record($run, $attempt, $input, $output, $latency, $requestId);
                    $this->fail($run, $failure->reason, $failure->getMessage());

                    return;
                }
                Sleep::for(2 ** $attempt)->seconds();
            }
        }
    }

    private function record(ContentGenerationRun $run, int $attempts, int $input, int $output, int $latency, ?string $requestId): void
    {
        $run->forceFill([
            'attempts' => $attempts,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'cost_usd' => $this->budget->cost($input, $output),
            'latency_ms' => $latency,
            'provider_request_id' => $requestId === null ? null : mb_substr($requestId, 0, 120),
        ])->save();
    }

    private function apply(ContentGenerationRun $run, ContentItem $item, GeneratedCopy $copy): void
    {
        DB::transaction(function () use ($run, $item, $copy): void {
            $item = ContentItem::query()->lockForUpdate()->findOrFail($item->id);
            $base = ContentRevision::query()->with('asset')->findOrFail($run->base_revision_id);
            // A draft is edited in place, so an unchanged id is not enough: an edit after the request also counts.
            $changed = $item->active_revision_id !== $base->id
                || $base->updated_at->greaterThan($run->created_at)
                || $item->status !== ContentStatus::Draft;
            if ($changed) {
                $run->forceFill([
                    'status' => GenerationStatus::Discarded,
                    'error_code' => 'content_changed',
                    'error_message' => 'Konten berubah saat AI bekerja, jadi hasilnya tidak diterapkan.',
                    'finished_at' => now(),
                ])->save();

                return;
            }

            $revision = $this->copier->copy($item, $base, $run->requester, [...$copy->values, 'generation_run_id' => $run->id]);
            $item->forceFill(['active_revision_id' => $revision->id])->save();
            $run->forceFill(['status' => GenerationStatus::Succeeded, 'result_revision_id' => $revision->id, 'finished_at' => now()])->save();

            ($this->audit)('content.generation_applied', 'content_item', $item->public_id, null,
                ['revision' => $base->number],
                [
                    'revision' => $revision->number,
                    'run' => $run->public_id,
                    'fields' => array_keys($copy->values),
                    'provider' => $run->provider,
                    'model' => $run->model,
                    'prompt_version' => $run->prompt_version,
                    'cost_usd' => (float) $run->cost_usd,
                ],
            );
        });
    }

    private function fail(ContentGenerationRun $run, string $code, string $message): void
    {
        $run->forceFill(['status' => GenerationStatus::Failed, 'error_code' => $code, 'error_message' => mb_substr($message, 0, 500), 'finished_at' => now()])->save();
        ($this->audit)('content.generation_failed', 'content_item', $run->item->public_id, null, null, ['run' => $run->public_id, 'error' => $code], $message);
    }
}
