<?php

declare(strict_types=1);

namespace App\Integrations\ContentResearch;

use App\Domain\Content\Contracts\WebSearch;
use App\Domain\Content\Research\SearchFailed;

/** CONTENT_SEARCH_PROVIDER=none (the default): web search is off; manual sources still work. */
final class UnconfiguredSearch implements WebSearch
{
    public function provider(): string
    {
        return 'none';
    }

    public function unavailableReason(): string
    {
        return 'Pencarian web belum dikonfigurasi. Sumber tetap dapat ditambahkan manual.';
    }

    public function search(string $query, int $maxResults): array
    {
        throw new SearchFailed('unconfigured', 'Pencarian web belum dikonfigurasi.');
    }
}
