<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Domain\Content\Models\ContentApproval;
use App\Domain\Content\Models\ContentAsset;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentPublication;
use App\Domain\Content\Models\ContentRevision;
use App\Domain\Content\Support\PostingQueue;
use App\Support\BusinessTime;

/**
 * The back-office contract for Content Studio; resources/js/types/content.ts mirrors it. Only content and the names
 * of the people who worked on it: no customer, order, or payment data is ever part of it.
 */
final class ContentPresenter
{
    /** @return array{code: string, name: string} */
    public static function brand(ContentBrand $brand): array
    {
        return ['code' => $brand->code, 'name' => $brand->name];
    }

    /**
     * Calendar and list rows. Needs brand, pillar, campaign, and activeRevision.asset loaded.
     *
     * @return array<string, mixed>
     */
    public static function row(ContentItem $item): array
    {
        return [
            'id' => $item->public_id,
            'title' => $item->title,
            'status' => $item->status->value,
            'brand' => self::brand($item->brand),
            'pillar' => $item->pillar === null ? null : ['id' => $item->pillar->public_id, 'name' => $item->pillar->name],
            'campaign' => $item->campaign === null ? null : ['id' => $item->campaign->public_id, 'name' => $item->campaign->name],
            'topic' => $item->topic,
            'plannedAt' => $item->planned_at->toIso8601ZuluString(),
            'plannedDay' => $item->planned_at->setTimezone(BusinessTime::zone())->toDateString(),
            'revisionNumber' => $item->activeRevision?->number,
            // Waiting to be posted by hand: overdue, today, or upcoming. A reminder only; it never publishes.
            'dueState' => app(PostingQueue::class)->dueState($item),
            'hasImage' => $item->activeRevision?->asset !== null,
            'updatedAt' => $item->updated_at->toIso8601ZuluString(),
        ];
    }

    /** @return array<string, mixed> */
    public static function item(ContentItem $item): array
    {
        return [
            ...self::row($item),
            'createdAt' => $item->created_at->toIso8601ZuluString(),
            'createdBy' => $item->creator->name,
            'approvedRevisionId' => $item->approved_revision_id === null ? null : $item->revisions->firstWhere('id', $item->approved_revision_id)?->public_id,
            'publication' => $item->publication === null ? null : self::publication($item->publication),
        ];
    }

    /**
     * Needs asset, decisions.actor, author, and submitter loaded.
     *
     * @return array<string, mixed>
     */
    public static function revision(ContentRevision $revision): array
    {
        return [
            'id' => $revision->public_id,
            'number' => $revision->number,
            'objective' => $revision->objective,
            'audience' => $revision->audience,
            'brief' => $revision->brief,
            'caption' => $revision->caption,
            'hashtags' => $revision->hashtags,
            'altText' => $revision->alt_text,
            'visualDirection' => $revision->visual_direction,
            'brandRulesVersion' => $revision->brandProfile?->version,
            'generatedBy' => $revision->generationRun === null ? null : [
                'provider' => $revision->generationRun->provider,
                'model' => $revision->generationRun->model,
                'promptVersion' => $revision->generationRun->prompt_version,
                'fields' => $revision->generationRun->fields,
            ],
            'author' => $revision->author->name,
            'submittedBy' => $revision->submitter?->name,
            'submittedAt' => $revision->submitted_at?->toIso8601ZuluString(),
            'createdAt' => $revision->created_at->toIso8601ZuluString(),
            'updatedAt' => $revision->updated_at->toIso8601ZuluString(),
            'asset' => $revision->asset === null ? null : self::asset($revision->asset),
            'decisions' => $revision->decisions->map(static fn (ContentApproval $decision): array => [
                'decision' => $decision->decision->value,
                'areas' => $decision->areas ?? [],
                'note' => $decision->note,
                'actor' => $decision->actor->name,
                'at' => $decision->created_at->toIso8601ZuluString(),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function asset(ContentAsset $asset): array
    {
        return [
            'id' => $asset->public_id,
            'url' => route('content.assets.show', $asset, false),
            'downloadUrl' => route('content.assets.show', ['asset' => $asset, 'download' => 1], false),
            'fileName' => $asset->downloadName(),
            'mimeType' => $asset->mime_type,
            'sizeBytes' => $asset->size_bytes,
            'width' => $asset->width,
            'height' => $asset->height,
            'checksum' => $asset->checksum_sha256,
        ];
    }

    /** @return array<string, mixed> */
    public static function publication(ContentPublication $publication): array
    {
        return [
            'publishedAt' => $publication->published_at->toIso8601ZuluString(),
            'url' => $publication->url,
            'note' => $publication->note,
            'checklist' => $publication->checklist,
            'actor' => $publication->actor->name,
            'revisionId' => $publication->revision->public_id,
            'revisionNumber' => $publication->revision->number,
            'recordedAt' => $publication->created_at->toIso8601ZuluString(),
        ];
    }
}
