<?php

declare(strict_types=1);

namespace App\Integrations\ContentResearch;

use App\Domain\Content\Contracts\WebSearch;
use App\Domain\Content\Research\SearchFailed;
use App\Domain\Content\Research\SearchHit;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Tavily web search (https://docs.tavily.com). Basic depth, no raw page content, no answer: only results with a
 * snippet, which UrlPolicy filters and cleans. The key never leaves Laravel; agents use research tools instead.
 */
final class TavilySearch implements WebSearch
{
    public function provider(): string
    {
        return 'tavily';
    }

    public function unavailableReason(): ?string
    {
        return blank(config('content.ai.search.api_key')) ? 'Pencarian web belum lengkap dikonfigurasi: CONTENT_SEARCH_API_KEY.' : null;
    }

    public function search(string $query, int $maxResults): array
    {
        try {
            $response = Http::withToken((string) config('content.ai.search.api_key'))
                ->acceptJson()
                ->timeout((int) config('content.ai.search.timeout_seconds'))
                ->post('https://api.tavily.com/search', array_filter([
                    'query' => $query,
                    'search_depth' => 'basic',
                    'max_results' => $maxResults,
                    'include_answer' => false,
                    'include_raw_content' => false,
                    'include_images' => false,
                    'country' => config('content.ai.search.country') ?: null,
                ], static fn (mixed $v): bool => $v !== null));
        } catch (ConnectionException) {
            throw new SearchFailed('unreachable', 'Layanan pencarian tidak dapat dihubungi atau tidak menjawab tepat waktu.');
        }

        $status = $response->status();
        if ($status === 401 || $status === 403) {
            throw new SearchFailed('auth', 'API key pencarian ditolak. Periksa CONTENT_SEARCH_API_KEY.');
        }
        if ($status === 429 || $status === 432 || $status === 433) {
            throw new SearchFailed('rate_limited', 'Kuota atau batas permintaan layanan pencarian tercapai.');
        }
        if (! $response->successful()) {
            throw new SearchFailed('server_error', "Layanan pencarian menolak permintaan ({$status}).");
        }

        $hits = [];
        foreach ((array) $response->json('results', []) as $result) {
            if (! is_array($result) || ! is_string($result['url'] ?? null)) {
                continue;
            }
            $hits[] = new SearchHit(
                $result['url'],
                is_string($result['title'] ?? null) ? $result['title'] : '',
                is_string($result['content'] ?? null) ? $result['content'] : '',
                is_numeric($result['score'] ?? null) ? (float) $result['score'] : null,
                is_string($result['published_date'] ?? null) ? $result['published_date'] : null,
            );
        }

        return $hits;
    }
}
