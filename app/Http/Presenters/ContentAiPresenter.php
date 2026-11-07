<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Domain\Content\Actions\RequestResearch;
use App\Domain\Content\Ai\CopyBudget;
use App\Domain\Content\Ai\CopyModel;
use App\Domain\Content\Ai\CopyModels;
use App\Domain\Content\Contracts\CopyGenerator;
use App\Domain\Content\Enums\GenerationStatus;
use App\Domain\Content\Models\ContentGenerationRun;
use App\Domain\Content\Models\ContentResearchRun;
use App\Support\BusinessTime;
use Carbon\Carbon;

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
            'instruction' => $run->instruction,
            'usedReviewNote' => $run->used_review_note,
            'feedback' => $run->feedback?->value,
            'feedbackReason' => $run->feedback_reason,
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

    /**
     * How AI drafts fared, per model and prompt version: people's verdicts and how many became approved revisions.
     * For comparing providers and prompts; nothing here changes a prompt.
     *
     * @return list<array<string, mixed>>
     */
    public static function evaluation(): array
    {
        return ContentGenerationRun::query()
            ->where('status', GenerationStatus::Succeeded)
            ->selectRaw('model, prompt_version, count(*) as drafts')
            ->selectRaw("sum(case when feedback = 'USEFUL' then 1 else 0 end) as useful")
            ->selectRaw("sum(case when feedback = 'NOT_USEFUL' then 1 else 0 end) as not_useful")
            ->selectRaw("sum(case when exists (select 1 from content_approvals a where a.content_revision_id = content_generation_runs.result_revision_id and a.decision = 'APPROVED') then 1 else 0 end) as approved")
            ->selectRaw('sum(cost_usd) as cost')
            ->groupBy('model', 'prompt_version')
            ->orderByDesc('drafts')
            ->get()
            ->map(static fn (ContentGenerationRun $row): array => [
                'model' => $row->model,
                'promptVersion' => $row->prompt_version,
                'drafts' => (int) $row->getAttribute('drafts'),
                'useful' => (int) $row->getAttribute('useful'),
                'notUseful' => (int) $row->getAttribute('not_useful'),
                'approved' => (int) $row->getAttribute('approved'),
                'costUsd' => round((float) $row->getAttribute('cost'), 6),
            ])->values()->all();
    }

    /** @return array<string, mixed> */
    public static function status(CopyGenerator $generator, CopyBudget $budget, CopyModels $models): array
    {
        $until = $budget->circuitOpenUntil();
        $selected = $models->selected();
        $real = $generator->provider() === 'openai_compatible';

        return [
            'provider' => $generator->provider(),
            'model' => $generator->model(),
            'unavailableReason' => $budget->unavailableReason(),
            'queue' => (string) config('content.ai.queue'),
            'priceInputPerMillion' => $real ? ($selected->priceInputPerMillion ?? 0.0) : 0.0,
            'priceOutputPerMillion' => $real ? ($selected->priceOutputPerMillion ?? 0.0) : 0.0,
            // Only a real provider has a choice; the list comes from the server's configuration.
            'models' => $real ? array_map(static fn (CopyModel $m): array => [
                'name' => $m->name,
                'priceInputPerMillion' => $m->priceInputPerMillion,
                'priceOutputPerMillion' => $m->priceOutputPerMillion,
            ], $models->available()) : [],
            'maxOutputTokens' => (int) config('content.ai.copy.max_output_tokens'),
            'maxAttempts' => (int) config('content.ai.copy.max_attempts'),
            'timeoutSeconds' => (int) config('content.ai.copy.timeout_seconds'),
            'budget' => [
                'spentThisWeek' => $budget->spentThisWeek(),
                'weekLimit' => $budget->weekLimit(),
                'runLimit' => $budget->runLimit(),
                'runsPerRevision' => (int) config('content.ai.budget.runs_per_revision'),
            ],
            'search' => [
                'provider' => (string) config('content.ai.search.provider'),
                'unavailableReason' => app(RequestResearch::class)->unavailableReason(),
                'pricePerCall' => (float) config('content.ai.search.price_per_call'),
                'maxResults' => (int) config('content.ai.search.max_results'),
                'perItemPerDay' => (int) config('content.ai.search.searches_per_item_per_day'),
                'callsThisWeek' => (int) ContentResearchRun::query()->where('started_at', '>=', now(BusinessTime::zone())->startOfWeek(Carbon::MONDAY)->startOfDay()->utc())->sum('search_calls'),
            ],
            'circuitOpenUntil' => $until !== null && $until->isFuture() ? $until->toIso8601ZuluString() : null,
        ];
    }
}
