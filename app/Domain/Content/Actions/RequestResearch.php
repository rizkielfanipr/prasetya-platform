<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Contracts\WebSearch;
use App\Domain\Content\Enums\ResearchStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Jobs\RunContentResearch;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentResearchRun;
use Illuminate\Support\Facades\DB;

/**
 * Queues a web search for an item's research (ADR-0011, phase 4). Runs on the AI queue, one at a time per item.
 * Works without Hermes.
 */
final class RequestResearch
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly WebSearch $search,
    ) {}

    public function __invoke(ContentItem $item, string $query, User $actor): ContentResearchRun
    {
        $query = trim((string) preg_replace('/\s+/u', ' ', $query));
        if (mb_strlen($query) < 3) {
            throw ContentActionRefused::because('Tulis kata kunci riset minimal 3 karakter.');
        }

        return DB::transaction(function () use ($item, $query, $actor): ContentResearchRun {
            $item = ContentItem::query()->lockForUpdate()->findOrFail($item->id);
            $reason = $this->reason($item);
            if ($reason !== null) {
                throw ContentActionRefused::because($reason);
            }

            $run = ContentResearchRun::query()->create([
                'content_brand_id' => $item->content_brand_id,
                'content_item_id' => $item->id,
                'query' => mb_substr($query, 0, 300),
                'status' => ResearchStatus::Queued,
                'provider' => $this->search->provider(),
                'max_results' => max(1, min(10, (int) config('content.ai.search.max_results'))),
                'requested_by_user_id' => $actor->id,
            ]);
            ($this->audit)('content.research_requested', 'content_item', $item->public_id, $actor, null, ['run' => $run->public_id, 'query' => $run->query, 'provider' => $run->provider]);
            RunContentResearch::dispatch($run->id)->onQueue((string) config('content.ai.queue'))->afterCommit();

            return $run;
        });
    }

    /** Why web research cannot be requested for this item now, or null. The screen shows the same answer. */
    public function reason(ContentItem $item): ?string
    {
        $configuration = $this->unavailableReason();
        if ($configuration !== null) {
            return $configuration;
        }
        if ($item->status->isFinal()) {
            return 'Riset tidak diperlukan untuk konten yang sudah diposting atau diarsipkan.';
        }
        $active = ContentResearchRun::query()->where('content_item_id', $item->id)
            ->whereIn('status', [ResearchStatus::Queued, ResearchStatus::Running])
            ->where('created_at', '>=', now()->subMinutes(RequestCopyGeneration::STALE_MINUTES))->exists();
        if ($active) {
            return 'Riset web sedang berjalan untuk konten ini.';
        }

        return null;
    }

    public function unavailableReason(): ?string
    {
        $reason = $this->search->unavailableReason();
        if ($reason !== null) {
            return $reason;
        }
        if ($this->search->provider() === 'fake' && app()->isProduction()) {
            return 'Pencarian contoh (fake) tidak boleh dipakai di production.';
        }

        return null;
    }

    /** Optional price per search for the cost record; 0 when not configured. */
    public function price(): float
    {
        return $this->search->provider() === 'fake' ? 0.0 : (float) config('content.ai.search.price_per_call');
    }
}
