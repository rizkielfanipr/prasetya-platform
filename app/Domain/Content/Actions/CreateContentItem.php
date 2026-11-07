<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Content\Models\ContentRevision;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Plans a new post: a draft item with an empty first revision. */
final class CreateContentItem
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function __invoke(ContentBrand $brand, string $title, CarbonInterface $plannedAt, User $actor): ContentItem
    {
        if (! $brand->is_active) {
            throw ContentActionRefused::because('Brand ini tidak aktif.');
        }

        return DB::transaction(function () use ($brand, $title, $plannedAt, $actor): ContentItem {
            $item = ContentItem::query()->create([
                'content_brand_id' => $brand->id,
                'title' => $title,
                'planned_at' => $plannedAt,
                'status' => ContentStatus::Draft,
                'created_by_user_id' => $actor->id,
            ]);
            $revision = ContentRevision::query()->create(['content_item_id' => $item->id, 'number' => 1, 'author_user_id' => $actor->id]);
            $item->forceFill(['active_revision_id' => $revision->id])->save();

            ($this->audit)('content.created', 'content_item', $item->public_id, $actor, null, [
                'brand' => $brand->code,
                'title' => $title,
                'planned_at' => $item->planned_at->toIso8601ZuluString(),
                'status' => ContentStatus::Draft->value,
            ]);

            return $item;
        });
    }
}
