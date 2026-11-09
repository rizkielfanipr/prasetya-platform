<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentBrandKnowledgeChunk;

/** Keyword retrieval for the first RAG stage; the chunk contract can gain embeddings later without changing callers. */
final class BrandKnowledgeRetriever
{
    /** @return list<string> */
    public function retrieve(ContentBrand $brand, string $query, int $limit = 6): array
    {
        $brand->loadMissing('currentKnowledge.chunks');
        $knowledge = $brand->currentKnowledge;
        if ($knowledge === null) {
            return [];
        }
        $chunks = $knowledge->chunks;
        if ($chunks->count() <= $limit) {
            return $chunks->pluck('content')->all();
        }

        $terms = collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($query)) ?: [])
            ->filter(static fn (string $term): bool => mb_strlen($term) >= 4)
            ->unique()
            ->values();
        if ($terms->isEmpty()) {
            return $chunks->take($limit)->pluck('content')->all();
        }

        return $chunks->map(static function (ContentBrandKnowledgeChunk $chunk) use ($terms): array {
            $content = mb_strtolower($chunk->content);
            $score = $terms->sum(static fn (string $term): int => substr_count($content, $term));

            return ['content' => $chunk->content, 'position' => $chunk->position, 'score' => $score];
        })->sortBy([['score', 'desc'], ['position', 'asc']])->take($limit)->pluck('content')->all();
    }
}
