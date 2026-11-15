<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Domain\Content\Models\ContentResearchSource;

/** Research sources and runs for the screen. Source text is untrusted and is rendered as plain text only. */
final class ContentResearchPresenter
{
    /** @return array<string, mixed> */
    public static function source(ContentResearchSource $source, ?string $note = null): array
    {
        return [
            'id' => $source->public_id,
            'url' => $source->url,
            'domain' => $source->domain,
            'title' => $source->title,
            'summary' => $source->summary,
            'trust' => $source->trust->value,
            'origin' => $source->origin->value,
            'relevance' => $source->relevance === null ? null : (float) $source->relevance,
            'publishedOn' => $source->published_on?->toDateString(),
            'fetchedAt' => $source->fetched_at->toIso8601ZuluString(),
            'note' => $note,
        ];
    }
}
