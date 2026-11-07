<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Enums\ContentDecision;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentApproval;
use App\Domain\Content\Models\ContentItem;

/** The revision request an item is answering now: the latest one, while the item is in REVISION_REQUESTED. */
final class RevisionRequestLookup
{
    public function current(ContentItem $item): ?ContentApproval
    {
        if ($item->status !== ContentStatus::RevisionRequested) {
            return null;
        }

        return ContentApproval::query()
            ->where('decision', ContentDecision::RevisionRequested)
            ->whereHas('revision', static fn ($q) => $q->where('content_item_id', $item->id))
            ->latest('id')
            ->first();
    }
}
