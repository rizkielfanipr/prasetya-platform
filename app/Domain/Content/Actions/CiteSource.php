<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentResearchSource;
use Illuminate\Support\Facades\DB;

/** Cites a research source on an item, or removes the citation. The source itself is kept for the brand. */
final class CiteSource
{
    public const int MAX_PER_ITEM = 8;

    public function __construct(private readonly RecordAudit $audit) {}

    public function __invoke(ContentItem $item, ContentResearchSource $source, ?string $note, User $actor): void
    {
        $this->guard($item, $source);
        DB::transaction(function () use ($item, $source, $note, $actor): void {
            if ($item->sources()->whereKey($source->id)->exists()) {
                return;
            }
            if ($item->sources()->count() >= self::MAX_PER_ITEM) {
                throw ContentActionRefused::because('Satu konten paling banyak memakai '.self::MAX_PER_ITEM.' sumber.');
            }
            $note = $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 500);
            $item->sources()->attach($source->id, ['note' => $note, 'linked_by_user_id' => $actor->id, 'created_at' => now()]);
            ($this->audit)('content.source_cited', 'content_item', $item->public_id, $actor, null, ['source' => $source->public_id, 'url' => $source->url, 'note' => $note]);
        });
    }

    public function remove(ContentItem $item, ContentResearchSource $source, User $actor): void
    {
        $this->guard($item, $source);
        DB::transaction(function () use ($item, $source, $actor): void {
            if ($item->sources()->detach($source->id) > 0) {
                ($this->audit)('content.source_removed', 'content_item', $item->public_id, $actor, ['source' => $source->public_id, 'url' => $source->url], null);
            }
        });
    }

    private function guard(ContentItem $item, ContentResearchSource $source): void
    {
        if ($source->content_brand_id !== $item->content_brand_id) {
            throw ContentActionRefused::because('Sumber ini milik brand lain.');
        }
        if ($item->status->isFinal()) {
            throw ContentActionRefused::because('Sumber konten yang sudah dipublikasikan atau ditolak tidak dapat diubah.');
        }
    }
}
