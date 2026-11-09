<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\ContentAvailability;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Support\ContentItemLock;
use App\Domain\Content\Support\ContentPlan;
use Illuminate\Support\Facades\DB;

/**
 * Changes an item's plan: working title, planned time, pillar, campaign, and topic. These describe the plan, not the post, so they do not create a
 * revision or affect an approval. A planned time passing never publishes anything.
 */
final class UpdateContentDetails
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly ContentAvailability $availability,
        private readonly ContentItemLock $lock,
    ) {}

    public function __invoke(ContentItem $item, ContentPlan $plan, User $actor): void
    {
        DB::transaction(function () use ($item, $plan, $actor): void {
            $item = $this->lock->lock($item, null);
            $this->availability->ensure($item, $actor, 'editDetails');

            $previous = $this->snapshot($item);
            $item->forceFill($plan->attributesFor($item->brand, $item->content_pillar_id, $item->content_campaign_id));
            if (! $item->isDirty()) {
                return;
            }
            $item->save();
            $after = $this->snapshot($item->load(['pillar', 'campaign']));

            ($this->audit)('content.details_updated', 'content_item', $item->public_id, $actor,
                array_diff_assoc($previous, $after),
                array_diff_assoc($after, $previous),
            );
        });
    }

    /** @return array<string, string|null> */
    private function snapshot(ContentItem $item): array
    {
        return [
            'title' => $item->title,
            'planned_at' => $item->planned_at->toIso8601ZuluString(),
            'pillar' => $item->pillar?->name,
            'campaign' => $item->campaign?->name,
            'topic' => $item->topic,
        ];
    }
}
