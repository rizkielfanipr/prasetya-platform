<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Auth\Models\User;
use App\Domain\Content\Models\ContentAsset;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;

/** Starts revision N+1 as a copy of a revision, image row included. The source is never changed. */
final class RevisionCopier
{
    /** @param  array<string, mixed>  $attributes  extra attributes for the new revision */
    public function copy(ContentItem $item, ContentRevision $from, User $author, array $attributes = []): ContentRevision
    {
        $number = (int) ContentRevision::query()->where('content_item_id', $item->id)->max('number') + 1;
        $revision = ContentRevision::query()->create([
            ...array_intersect_key($from->getAttributes(), array_flip(ContentRevision::FIELDS)),
            ...$attributes,
            'content_item_id' => $item->id,
            'number' => $number,
            'author_user_id' => $author->id,
        ]);

        $asset = $from->asset;
        if ($asset !== null) {
            ContentAsset::query()->create([
                ...array_intersect_key($asset->getAttributes(), array_flip(['disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'width', 'height', 'checksum_sha256', 'uploaded_by_user_id'])),
                'content_revision_id' => $revision->id,
            ]);
        }

        return $revision;
    }
}
