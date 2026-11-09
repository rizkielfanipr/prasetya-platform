<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Content\Contracts\WebSearch;
use App\Domain\Content\Enums\ResearchStatus;
use App\Domain\Content\Enums\SourceOrigin;
use App\Domain\Content\Models\ContentResearchRun;
use App\Domain\Content\Models\ContentResearchSource;
use App\Domain\Content\Research\SearchFailed;
use App\Domain\Content\Research\UrlPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Runs one queued search outside any transaction, then keeps only results the URL policy allows, as sources with a
 * short cleaned summary (never the article), trust, and relevance. A page already known for the brand is reused.
 */
final class RunResearch
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly WebSearch $search,
        private readonly UrlPolicy $policy,
        private readonly RequestResearch $requests,
    ) {}

    public function __invoke(int $runId): void
    {
        $run = DB::transaction(function () use ($runId): ?ContentResearchRun {
            $run = ContentResearchRun::query()->lockForUpdate()->find($runId);
            if ($run === null || $run->status !== ResearchStatus::Queued) {
                return null;
            }
            $run->forceFill(['status' => ResearchStatus::Running, 'started_at' => now()])->save();

            return $run;
        });
        if ($run === null) {
            return;
        }

        $started = hrtime(true);
        try {
            $hits = $this->search->search($run->query, $run->max_results);
        } catch (SearchFailed $failed) {
            $run->forceFill([
                'status' => ResearchStatus::Failed, 'search_calls' => 1, 'cost_usd' => $this->requests->price(),
                'error_code' => $failed->reason, 'error_message' => mb_substr($failed->getMessage(), 0, 500),
                'latency_ms' => (int) ((hrtime(true) - $started) / 1_000_000), 'finished_at' => now(),
            ])->save();
            $this->audited($run, 'content.research_failed', ['run' => $run->public_id, 'error' => $failed->reason], $failed->getMessage());

            return;
        }
        $latency = (int) ((hrtime(true) - $started) / 1_000_000);
        $summaryMax = (int) config('content.research.summary_max_chars');

        DB::transaction(function () use ($run, $hits, $latency, $summaryMax): void {
            $ids = [];
            $dropped = 0;
            foreach ($hits as $hit) {
                if ($this->policy->refusal($hit->url) !== null || trim($hit->title) === '') {
                    $dropped++;

                    continue;
                }
                $url = $this->policy->canonical($hit->url);
                $hash = $this->policy->identity($url);
                $source = ContentResearchSource::query()->where('content_brand_id', $run->content_brand_id)->where('url_hash', $hash)->first()
                    ?? ContentResearchSource::query()->create([
                        'content_brand_id' => $run->content_brand_id,
                        'content_research_run_id' => $run->id,
                        'origin' => SourceOrigin::Search,
                        'url' => $url,
                        'url_hash' => $hash,
                        'domain' => $this->policy->domain($url),
                        'title' => $this->policy->clean($hit->title, 300),
                        'summary' => $this->policy->clean($hit->snippet, $summaryMax) ?: null,
                        'trust' => $this->policy->trust($url),
                        'relevance' => $hit->score === null ? null : max(0.0, min(1.0, $hit->score)),
                        'published_on' => $this->date($hit->publishedDate),
                        'fetched_at' => now(),
                        'added_by_user_id' => $run->requested_by_user_id,
                    ]);
                $ids[] = $source->id;
            }
            $ids = array_values(array_unique($ids));
            $run->forceFill([
                'status' => ResearchStatus::Succeeded, 'search_calls' => 1, 'cost_usd' => $this->requests->price(),
                'results_count' => count($ids), 'dropped_count' => $dropped, 'result_source_ids' => $ids,
                'latency_ms' => $latency, 'finished_at' => now(),
            ])->save();
            $this->audited($run, 'content.research_completed', ['run' => $run->public_id, 'kept' => count($ids), 'dropped' => $dropped]);
        });
    }

    /** @param  array<string, mixed>  $new */
    private function audited(ContentResearchRun $run, string $action, array $new, ?string $reason = null): void
    {
        ($this->audit)($action, 'content_item', (string) $run->item?->public_id, null, null, $new, $reason);
    }

    private function date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
