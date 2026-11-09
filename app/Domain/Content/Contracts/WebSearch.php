<?php

declare(strict_types=1);

namespace App\Domain\Content\Contracts;

use App\Domain\Content\Research\SearchFailed;
use App\Domain\Content\Research\SearchHit;

/** Web search for research (phase 4). Adapters live in App\Integrations\ContentResearch and hold the key. */
interface WebSearch
{
    public function provider(): string;

    /** Why search cannot be used, or null. Never contains a secret. */
    public function unavailableReason(): ?string;

    /**
     * @return list<SearchHit>
     *
     * @throws SearchFailed
     */
    public function search(string $query, int $maxResults): array;
}
