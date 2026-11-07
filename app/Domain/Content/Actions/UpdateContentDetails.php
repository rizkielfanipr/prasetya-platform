<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\ContentAvailability;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Support\ContentItemLock;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Changes an item's working title or planned time. These describe the plan, not the post, so they do not create a
 * revision or affect an approval. A planned time passing never publishes anything.
 */
final class UpdateContentDetails
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly ContentAvailability $availability,
        private readonly ContentItemLock $lock,
    ) {}

    public function __invoke(ContentItem $item, string $title, CarbonInterface $plannedAt, User $actor): void
    {
        DB::transaction(function () use ($item, $title, $plannedAt, $actor): void {
            $item = $this->lock->lock($item, null);
            $this->availability->ensure($item, $actor, 'editDetails');

            $previous = ['title' => $item->title, 'planned_at' => $item->planned_at->toIso8601ZuluString()];
            $item->forceFill(['title' => $title, 'planned_at' => $plannedAt]);
            if (! $item->isDirty()) {
                return;
            }
            $item->save();

            ($this->audit)('content.details_updated', 'content_item', $item->public_id, $actor, $previous, [
                'title' => $item->title,
                'planned_at' => $item->planned_at->toIso8601ZuluString(),
            ]);
        });
    }
}
