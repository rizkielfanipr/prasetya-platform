<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Domain\Content\Ai\CopyBudget;
use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Models\ContentGenerationRun;

/** AI generation runs and status for the screen. Never a key, a prompt, or a raw provider response. */
final class ContentAiPresenter
{
    /** @return array<string, mixed> */
    public static function run(ContentGenerationRun $run): array
    {
        return [
            'id' => $run->public_id,
            'status' => $run->status->value,
            'fields' => $run->fields,
            'provider' => $run->provider,
            'model' => $run->model,
            'promptVersion' => $run->prompt_version,
            'inputTokens' => $run->input_tokens,
            'outputTokens' => $run->output_tokens,
            'costUsd' => (float) $run->cost_usd,
            'latencyMs' => $run->latency_ms,
            'attempts' => $run->attempts,
            'errorCode' => $run->error_code,
            'errorMessage' => $run->error_message,
            'requestedBy' => $run->requester->name,
            'resultRevisionNumber' => $run->resultRevision?->number,
            'item' => ['id' => $run->item->public_id, 'title' => $run->item->title],
            'createdAt' => $run->created_at->toIso8601ZuluString(),
            'finishedAt' => $run->finished_at?->toIso8601ZuluString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function status(CopyGenerator $generator, CopyBudget $budget): array
    {
        $until = $budget->circuitOpenUntil();

        return [
            'provider' => $generator->provider(),
            'model' => $generator->model(),
            'unavailableReason' => $budget->unavailableReason(),
            'queue' => (string) config('content.ai.queue'),
            'priceInputPerMillion' => (float) config('content.ai.copy.price_input_per_million'),
            'priceOutputPerMillion' => (float) config('content.ai.copy.price_output_per_million'),
            'maxOutputTokens' => (int) config('content.ai.copy.max_output_tokens'),
            'maxAttempts' => (int) config('content.ai.copy.max_attempts'),
            'timeoutSeconds' => (int) config('content.ai.copy.timeout_seconds'),
            'budget' => [
                'spentThisWeek' => $budget->spentThisWeek(),
                'weekLimit' => $budget->weekLimit(),
                'runLimit' => $budget->runLimit(),
                'runsPerRevision' => (int) config('content.ai.budget.runs_per_revision'),
            ],
            'circuitOpenUntil' => $until !== null && $until->isFuture() ? $until->toIso8601ZuluString() : null,
        ];
    }
}
