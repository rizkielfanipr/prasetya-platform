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
use App\Domain\Content\Support\ContentPlan;
use App\Support\BusinessTime;
use Illuminate\Support\Facades\DB;

/** Plans a new post: a draft item whose first revision holds the idea as its brief, or nothing. */
final class CreateContentItem
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function __invoke(ContentBrand $brand, ContentPlan $plan, User $actor, ?string $brief = null): ContentItem
    {
        if (! $brand->is_active) {
            throw ContentActionRefused::because('Brand ini tidak aktif.');
        }
        $attributes = $plan->attributesFor($brand);
        // A new post is offered only campaigns that run on its day (WIB); an existing one keeps a warning instead.
        if ($plan->campaign !== null && ! $plan->campaign->covers($plan->plannedAt->setTimezone(BusinessTime::zone()))) {
            throw ContentActionRefused::because("Campaign \"{$plan->campaign->name}\" tidak berjalan pada tanggal ini.");
        }

        $brief = $brief === null || trim($brief) === '' ? null : trim(str_replace("\r\n", "\n", $brief));

        return DB::transaction(function () use ($brand, $plan, $attributes, $actor, $brief): ContentItem {
            $item = ContentItem::query()->create([
                ...$attributes,
                'content_brand_id' => $brand->id,
                'status' => ContentStatus::Draft,
                'created_by_user_id' => $actor->id,
            ]);
            $revision = ContentRevision::query()->create(['content_item_id' => $item->id, 'number' => 1, 'author_user_id' => $actor->id, 'brief' => $brief]);
            $item->forceFill(['active_revision_id' => $revision->id])->save();

            ($this->audit)('content.created', 'content_item', $item->public_id, $actor, null, [
                'brand' => $brand->code,
                'title' => $item->title,
                'planned_at' => $item->planned_at->toIso8601ZuluString(),
                'pillar' => $plan->pillar?->name,
                'campaign' => $plan->campaign?->name,
                'topic' => $item->topic,
                'from_idea' => $brief !== null,
                'status' => ContentStatus::Draft->value,
            ]);

            return $item;
        });
    }
}
