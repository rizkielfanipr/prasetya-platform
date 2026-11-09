<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Contracts\PageReader;
use App\Domain\Content\Enums\SourceOrigin;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentResearchSource;
use App\Domain\Content\Research\SearchFailed;
use App\Domain\Content\Research\UrlPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Adds a source someone found themselves (for example with an AI assistant's web search) and cites it on the item.
 * When no title is given, the server reads the page title through the SSRF-safe reader, before any transaction.
 */
final class AddManualSource
{
    public function __construct(
        private readonly RecordAudit $audit,
        private readonly UrlPolicy $policy,
        private readonly PageReader $reader,
        private readonly CiteSource $cite,
    ) {}

    public function __invoke(ContentItem $item, string $url, ?string $title, ?string $summary, ?string $note, User $actor): ContentResearchSource
    {
        $refusal = $this->policy->refusal($url);
        if ($refusal !== null) {
            throw ContentActionRefused::because($refusal);
        }
        $title = $title === null ? '' : $this->policy->clean($title, 300);
        $summary = $summary === null ? '' : $this->policy->clean($summary, (int) config('content.research.summary_max_chars'));
        if ($title === '') {
            try {
                $page = $this->reader->read($url);
            } catch (SearchFailed $failed) {
                throw ContentActionRefused::because("Judul tidak dapat diambil otomatis: {$failed->getMessage()} Isi judulnya sendiri.");
            }
            $title = $this->policy->clean((string) $page['title'], 300);
            $summary = $summary !== '' ? $summary : $this->policy->clean((string) $page['description'], (int) config('content.research.summary_max_chars'));
            if ($title === '') {
                throw ContentActionRefused::because('Halaman ini tidak punya judul. Isi judulnya sendiri.');
            }
        }

        $source = DB::transaction(function () use ($item, $url, $title, $summary, $actor): ContentResearchSource {
            $canonical = $this->policy->canonical($url);
            $hash = $this->policy->identity($canonical);
            $existing = ContentResearchSource::query()->where('content_brand_id', $item->content_brand_id)->where('url_hash', $hash)->first();
            if ($existing !== null) {
                return $existing;
            }
            $source = ContentResearchSource::query()->create([
                'content_brand_id' => $item->content_brand_id,
                'origin' => SourceOrigin::Manual,
                'url' => $canonical,
                'url_hash' => $hash,
                'domain' => $this->policy->domain($canonical),
                'title' => $title,
                'summary' => $summary === '' ? null : $summary,
                'trust' => $this->policy->trust($canonical),
                'fetched_at' => now(),
                'added_by_user_id' => $actor->id,
            ]);
            ($this->audit)('content.source_added', 'content_item', $item->public_id, $actor, null, ['source' => $source->public_id, 'url' => $source->url, 'origin' => 'MANUAL']);

            return $source;
        });
        ($this->cite)($item, $source, $note, $actor);

        return $source;
    }
}
