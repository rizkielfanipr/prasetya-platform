<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\ContentFactCategory;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentCampaign;
use App\Domain\Content\Models\ContentFact;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Records an approved business fact that content may state, with where it comes from. Facts are the only source of
 * prices, product names, and promos for content; AI output never adds to them (ADR-0011).
 */
final class AddContentFact
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function __invoke(ContentBrand $brand, ?ContentCampaign $campaign, string $statement, string $source, ?CarbonInterface $validUntil, User $actor, ContentFactCategory $category = ContentFactCategory::General): ContentFact
    {
        if ($campaign !== null && $campaign->content_brand_id !== $brand->id) {
            throw ContentActionRefused::because('Campaign ini milik brand lain.');
        }

        return DB::transaction(function () use ($brand, $campaign, $statement, $source, $validUntil, $actor, $category): ContentFact {
            $fact = ContentFact::query()->create([
                'content_brand_id' => $brand->id,
                'content_campaign_id' => $campaign?->id,
                'category' => $category,
                'statement' => trim($statement),
                'source' => trim($source),
                'valid_until' => $validUntil?->toDateString(),
                'created_by_user_id' => $actor->id,
            ]);

            ($this->audit)('content.fact_added', 'content_brand', $brand->public_id, $actor, null, [
                'fact' => $fact->public_id,
                'category' => $fact->category->value,
                'statement' => $fact->statement,
                'source' => $fact->source,
                'campaign' => $campaign?->name,
                'valid_until' => $fact->valid_until?->toDateString(),
            ]);

            return $fact;
        });
    }
}
