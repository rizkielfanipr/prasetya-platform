<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\Actions\RecordAudit;
use App\Domain\Auth\Models\User;
use App\Domain\Content\Enums\CampaignPriority;
use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentCampaign;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** Adds or changes a campaign: name, objective, period (business-time days, inclusive), and priority. */
final class SaveCampaign
{
    public function __construct(private readonly RecordAudit $audit) {}

    public function __invoke(
        ContentBrand $brand,
        ?ContentCampaign $campaign,
        string $name,
        ?string $objective,
        CarbonInterface $startsOn,
        CarbonInterface $endsOn,
        CampaignPriority $priority,
        bool $isActive,
        User $actor,
    ): ContentCampaign {
        if ($campaign !== null && $campaign->content_brand_id !== $brand->id) {
            throw ContentActionRefused::because('Campaign ini milik brand lain.');
        }
        if ($endsOn->toDateString() < $startsOn->toDateString()) {
            throw ContentActionRefused::because('Tanggal selesai campaign tidak boleh sebelum tanggal mulai.');
        }

        return DB::transaction(function () use ($brand, $campaign, $name, $objective, $startsOn, $endsOn, $priority, $isActive, $actor): ContentCampaign {
            $campaign ??= new ContentCampaign(['content_brand_id' => $brand->id]);
            $snapshot = static fn (ContentCampaign $c): array => [
                'name' => $c->name,
                'objective' => $c->objective,
                'starts_on' => $c->starts_on?->toDateString(),
                'ends_on' => $c->ends_on?->toDateString(),
                'priority' => $c->priority?->value,
                'is_active' => $c->is_active,
            ];
            $previous = $campaign->exists ? $snapshot($campaign) : null;
            $campaign->fill(['name' => $name, 'objective' => $objective, 'starts_on' => $startsOn->toDateString(), 'ends_on' => $endsOn->toDateString(), 'priority' => $priority, 'is_active' => $isActive]);
            if ($campaign->exists && ! $campaign->isDirty()) {
                return $campaign;
            }
            $campaign->save();
            $after = $snapshot($campaign->refresh());

            ($this->audit)($previous === null ? 'content.campaign_created' : 'content.campaign_updated', 'content_brand', $brand->public_id, $actor,
                $previous === null ? null : array_diff_assoc($previous, $after),
                ['campaign' => $campaign->name] + ($previous === null ? $after : array_diff_assoc($after, $previous)),
            );

            return $campaign;
        });
    }
}
