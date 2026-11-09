<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentItem;

/**
 * Locks an item for one workflow action and refuses a request made against an older revision than the current one,
 * so two people acting on the same item never decide on content they have not seen. Call inside a transaction.
 */
final class ContentItemLock
{
    public function lock(ContentItem $item, ?string $expectedRevision): ContentItem
    {
        $locked = ContentItem::query()->lockForUpdate()->findOrFail($item->id);
        $locked->load('activeRevision.asset');

        if ($expectedRevision !== null && $locked->activeRevision?->public_id !== $expectedRevision) {
            throw ContentActionRefused::staleRevision();
        }

        return $locked;
    }
}
