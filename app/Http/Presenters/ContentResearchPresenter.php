<?php

declare(strict_types=1);

namespace App\Http\Presenters;

use App\Domain\Content\Models\ContentResearchRun;
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

    /**
     * @param  iterable<ContentResearchSource>  $sources  the run's results, keyed by id
     * @return array<string, mixed>
     */
    public static function run(ContentResearchRun $run, iterable $sources): array
    {
        $byId = [];
        foreach ($sources as $source) {
            $byId[$source->id] = $source;
        }

        return [
            'id' => $run->public_id,
            'query' => $run->query,
            'status' => $run->status->value,
            'provider' => $run->provider,
            'resultsCount' => $run->results_count,
            'droppedCount' => $run->dropped_count,
            'costUsd' => (float) $run->cost_usd,
            'errorMessage' => $run->error_message,
            'requestedBy' => $run->requester->name,
            'createdAt' => $run->created_at->toIso8601ZuluString(),
            'sources' => array_values(array_map(
                static fn (int $id): array => self::source($byId[$id]),
                array_values(array_filter($run->result_source_ids ?? [], static fn (int $id): bool => isset($byId[$id]))),
            )),
        ];
    }
}
