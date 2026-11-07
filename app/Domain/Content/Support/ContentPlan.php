<?php

declare(strict_types=1);

namespace App\Domain\Content\Support;

use App\Domain\Content\Exceptions\ContentActionRefused;
use App\Domain\Content\Models\ContentBrand;
use App\Domain\Content\Models\ContentCampaign;
use App\Domain\Content\Models\ContentPillar;
use Carbon\CarbonInterface;

/** An item's plan: title, planned time, and optionally its pillar, campaign, and topic. */
final readonly class ContentPlan
{
    public function __construct(
        public string $title,
        public CarbonInterface $plannedAt,
        public ?ContentPillar $pillar = null,
        public ?ContentCampaign $campaign = null,
        public ?string $topic = null,
    ) {}

    /**
     * A pillar or campaign must belong to the item's brand. Choosing an inactive one is refused, but an item that
     * already has it may keep it.
     *
     * @return array{title: string, planned_at: CarbonInterface, content_pillar_id: int|null, content_campaign_id: int|null, topic: string|null}
     */
    public function attributesFor(ContentBrand $brand, ?int $currentPillarId = null, ?int $currentCampaignId = null): array
    {
        foreach ([[$this->pillar, $currentPillarId, 'Pilar'], [$this->campaign, $currentCampaignId, 'Kampanye']] as [$choice, $current, $label]) {
            if ($choice === null) {
                continue;
            }
            if ($choice->content_brand_id !== $brand->id) {
                throw ContentActionRefused::because("{$label} ini milik brand lain.");
            }
            if (! $choice->is_active && $choice->id !== $current) {
                throw ContentActionRefused::because("{$label} \"{$choice->name}\" sudah tidak aktif.");
            }
        }
        $topic = $this->topic === null ? null : trim($this->topic);

        return [
            'title' => $this->title,
            'planned_at' => $this->plannedAt,
            'content_pillar_id' => $this->pillar?->id,
            'content_campaign_id' => $this->campaign?->id,
            'topic' => $topic === '' ? null : $topic,
        ];
    }
}
